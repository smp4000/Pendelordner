<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\MatchingRule;
use App\Models\Receipt;
use App\Services\Bank\BankImportService;
use App\Services\Bank\Parsers\CamtParser;
use App\Services\Bank\Parsers\CsvBankParser;
use App\Services\Bank\Parsers\Mt940Parser;
use App\Services\Bank\FinTsErrorTranslator;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\ReceiptParser;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);
    }

    private function account(): BankAccount
    {
        return BankAccount::create([
            'label' => 'Testkonto',
            'iban' => 'DE00100000000000000001',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);
    }

    public function test_csv_import_mit_dublettenpruefung_und_regeln(): void
    {
        $csv = "Buchungstag;Valutadatum;Name Zahlungsbeteiligter;Verwendungszweck;IBAN Zahlungsbeteiligter;Betrag;Waehrung\n"
            . "02.01.2026;02.01.2026;HBW Sinsheim;Blumen Lieferung;DE12500105170648489890;-63,70;EUR\n"
            . "03.01.2026;03.01.2026;Telekom Deutschland GmbH;Telefon Januar;DE02300209000106531065;-89,95;EUR\n";

        $rows = (new CsvBankParser())->parse($csv);
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(-63.70, $rows[0]['amount'], 0.001);

        $service = new BankImportService();
        $account = $this->account();

        $log = $service->import($account, $rows, ImportSource::Csv, 'test.csv');
        $this->assertSame(2, $log->new_count);
        $this->assertSame(0, $log->duplicate_count);

        // Auto-Vorkontierung: HBW -> Blumen
        $hbw = $account->bankTransactions()->where('counterparty', 'HBW Sinsheim')->first();
        $this->assertSame('Blumen', $hbw->category->name);

        // Re-Import derselben Datei -> alles Dubletten
        $log2 = $service->import($account, $rows, ImportSource::Csv, 'test.csv');
        $this->assertSame(0, $log2->new_count);
        $this->assertSame(2, $log2->duplicate_count);
        $this->assertSame(2, $account->bankTransactions()->count());
    }

    public function test_mt940_doppelter_import_erkennt_dubletten(): void
    {
        $mt940 = implode("\n", [
            ':20:STARTUMS',
            ':25:50010517/0648489890',
            ':28C:00001/001',
            ':60F:C260101EUR1000,00',
            ':61:2601020102D63,70NMSCNONREF',
            ':86:177?00SEPA-LASTSCHRIFT?20Blumen Lieferung?32HBW Sinsheim',
            ':62F:C260102EUR936,30',
        ]);
        $rows = (new Mt940Parser())->parse($mt940);
        $account = $this->account();
        $service = new BankImportService();

        $service->import($account, $rows, ImportSource::Mt940);
        $log2 = $service->import($account, $rows, ImportSource::Mt940);

        $this->assertSame(1, $account->bankTransactions()->count());
        $this->assertSame(1, $log2->duplicate_count);
        $this->assertSame(0, $log2->new_count);
    }

    public function test_dublette_wird_format_uebergreifend_ueber_referenz_erkannt(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        // 1. Import (z. B. als CSV)
        $service->import($account, [[
            'booking_date' => '2026-06-10',
            'amount' => -50.00,
            'counterparty' => 'ARAL AG',
            'purpose' => 'Tankkauf',
            'bank_reference' => 'NDDR12345',
        ]], ImportSource::Csv, applyRules: false);

        // 2. Import derselben Buchung aus anderem Format (andere Texte, gleiche
        // Referenz/Datum/Betrag) -> muss als Dublette erkannt werden.
        $log2 = $service->import($account, [[
            'booking_date' => '2026-06-10',
            'amount' => -50.00,
            'counterparty' => 'ARAL AKTIENGESELLSCHAFT',
            'purpose' => 'EREF+NDDR12345 SVWZ+Kraftstoff',
            'bank_reference' => 'NDDR12345',
        ]], ImportSource::Mt940, applyRules: false);

        $this->assertSame(1, $account->bankTransactions()->count());
        $this->assertSame(1, $log2->duplicate_count);
    }

    public function test_wiederherstellung_behaelt_verknuepfte_belege_und_zuordnung(): void
    {
        $account = $this->account();
        $service = new BankImportService();
        $row = [
            'booking_date' => '2026-06-01',
            'amount' => -63.70,
            'counterparty' => 'HBW Sinsheim',
            'purpose' => 'Blumen',
            'bank_reference' => 'REF-1',
        ];

        $service->import($account, [$row], ImportSource::Csv, applyRules: false);
        $tx = $account->bankTransactions()->first();

        // Beleg verknüpfen + Kategorie setzen (manuelle Zuordnung).
        $receipt = Receipt::create(['type' => 'incoming_invoice', 'gross_amount' => 63.70]);
        $tx->receipts()->attach($receipt->id, ['amount' => 63.70]);
        $category = Category::firstOrCreate(['name' => 'Blumen'], ['active' => true]);
        $tx->update(['category_id' => $category->id]);

        // Versehentlich löschen (Soft-Delete) und dieselbe Datei erneut importieren.
        $tx->delete();
        $this->assertSame(0, $account->bankTransactions()->count());

        $service->import($account, [$row], ImportSource::Csv, applyRules: false);

        $tx->refresh();
        $this->assertNull($tx->deleted_at);                       // wiederhergestellt
        $this->assertSame(1, $tx->receipts()->count());           // Beleg weiterhin verknüpft
        $this->assertSame($category->id, $tx->category_id);       // Kategorie erhalten
        $this->assertSame(1, $account->bankTransactions()->count()); // kein Duplikat
    }

    public function test_reimport_stellt_geloeschte_umsaetze_wieder_her(): void
    {
        $csv = "Buchungstag;Valutadatum;Name Zahlungsbeteiligter;Verwendungszweck;IBAN Zahlungsbeteiligter;Betrag;Waehrung\n"
            . "02.01.2026;02.01.2026;HBW Sinsheim;Blumen Lieferung;DE12500105170648489890;-63,70;EUR\n";

        $rows = (new CsvBankParser())->parse($csv);
        $service = new BankImportService();
        $account = $this->account();

        $service->import($account, $rows, ImportSource::Csv, 'test.csv');
        $this->assertSame(1, $account->bankTransactions()->count());

        // Umsatz löschen (Soft-Delete) – Unique-Index bleibt bestehen.
        $account->bankTransactions()->first()->delete();
        $this->assertSame(0, $account->bankTransactions()->count());

        // Re-Import darf nicht am Unique-Index scheitern, sondern stellt
        // den gelöschten Umsatz wieder her.
        $log = $service->import($account, $rows, ImportSource::Csv, 'test.csv');
        $this->assertSame(0, $log->error_count);
        $this->assertSame(1, $log->new_count); // wiederhergestellt zählt als neu aktiv
        $this->assertSame(1, $account->bankTransactions()->count());
    }

    public function test_regel_wird_auf_vorhandene_umsaetze_angewendet(): void
    {
        $account = $this->account();
        $category = Category::firstOrCreate(['name' => 'Versicherung'], ['active' => true]);

        $make = fn (bool $reviewed) => BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-01',
            'counterparty' => 'Allianz Versicherungs-AG',
            'purpose' => 'Vertrag',
            'amount' => -120.27,
            'reviewed' => $reviewed,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $offen = $make(false);
        $geprueft = $make(true);

        $rule = MatchingRule::create([
            'pattern' => 'Allianz',
            'pattern_type' => 'counterparty',
            'category_id' => $category->id,
            'priority' => 10,
            'active' => true,
        ]);

        $changed = (new MatchingEngine())->applyRuleToExisting($rule, onlyUnreviewed: true);

        $this->assertSame(1, $changed);
        $this->assertSame($category->id, $offen->refresh()->category_id);
        $this->assertNull($geprueft->refresh()->category_id); // geprüfte bleiben unberührt
    }

    public function test_matching_per_belegnummer_im_verwendungszweck(): void
    {
        $account = $this->account();

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-26',
            'counterparty' => 'Haufe Service Center GmbH via Mollie',
            'purpose' => 'lx2026060362760 MZKZ-KWXR Lexware Office EREF: SD04-9895',
            'amount' => -10.37,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $receipt = Receipt::create([
            'type' => 'incoming_invoice',
            'invoice_number' => 'lx2026060362760',
            'gross_amount' => 10.37,
            'invoice_date' => '2026-06-24',
        ]);

        $score = (new MatchingEngine())->scoreReceipt($tx, $receipt);

        // Belegnummer im Verwendungszweck (+50) und Betrag exakt (+50) -> sehr hoch.
        $this->assertGreaterThanOrEqual(90.0, $score);
    }

    public function test_receipt_parser_ignoriert_pdf_marker(): void
    {
        // "<>"-Pseudomarker dürfen Schlüsselwörter nicht zerstören; der
        // Gesamtbetrag (10,37) muss vor "Gesamt Netto" (8,71) gewinnen.
        $text = "Gesamt Netto 8,71\nMwSt. 19% von 8,71 EUR 1,66\nGesamtbetr<>ag EUR 10,37";

        $data = (new ReceiptParser())->extract($text);
        $this->assertEqualsWithDelta(10.37, $data['gross_amount'], 0.001);
    }

    public function test_parser_zahlbetrag_vor_zahlungsvereinbarung(): void
    {
        // Hall-Tabakwaren-Layout: KVP-Summe (7.176,68) ist der größte Betrag im
        // Text, der echte Zahlbetrag steht VOR "Zahlungsvereinbarung".
        $text = "7.176,68205\t5.422,05Gesamt\n"
            . "MwSt\tNettobetrag €\tMwSt %\tMwSt €\tEndbetrag €\n"
            . "-35,70-5,7019,00-30,001\n"
            . "6.487,941.035,8919,005.452,051\n"
            . "6.452,24Zahlungsvereinbarung: SEPA-Firmenlastschrift  nach 9 Tagen";

        $data = (new ReceiptParser())->extract($text);
        $this->assertEqualsWithDelta(6452.24, $data['gross_amount'], 0.001);
    }

    public function test_parser_kundennummer_in_tabellenlayouts(): void
    {
        $parser = new ReceiptParser();

        // Inline (klassisch): Label, dann Wert.
        $this->assertSame('A8319', $parser->extract("Kundennummer  A8319\nBetrag 1,00")['customer_number']);

        // Wert VOR dem Label (Hall-Tabakwaren-Layout, Blatt 1).
        $text = "36100 Petersberg\n11145\nKundennr.\nDatum entspricht Rechnungsdatum und Lieferdatum";
        $this->assertSame('11145', $parser->extract($text)['customer_number']);

        // Tabellenkopf "Kundennr. Datum", Wert mit Datum verklebt in Folgezeile.
        $text = "RECHNUNG\nKundennr. Datum\n1114503.07.26\nChristian Welle";
        $this->assertSame('11145', $parser->extract($text)['customer_number']);

        // Wörter wie "Datum" hinter dem Label sind KEINE Kundennummer.
        $this->assertNull($parser->extract("Kundennr. Datum\nkein Wert weit und breit")['customer_number']);
    }

    public function test_parser_erkennt_ustidnr_und_lieferantennamen(): void
    {
        $text = "Haufe Service Center GmbH\nMunzinger Straße 9\n79111 Freiburg\n"
            . "USt-IdNr.: DE 812499727\nRechnungsnummer lx2026060362760";

        $parser = new ReceiptParser();
        $this->assertSame('DE812499727', $parser->vatId($text));
        $this->assertSame('Haufe Service Center GmbH', $parser->supplierNameGuess($text));
    }

    public function test_parser_erkennt_zahlbetrag_in_summentabelle(): void
    {
        // Lekkerland-Stil: mehrspaltige Summenzeile, Zahlbetrag steht zuletzt
        // vor "EUR" (1.946,57), nicht der Warenwert (2.010,00).
        $text = "Gesamtbetrag\n  2.010,00     0,00%  2.010,00       0,00      0,00  2.010,00\n"
            . "  2.010,00  1.956,70       0,00     10,13-   1.946,57 EUR\n"
            . "der Lekkerland SE * Europaallee 57";

        $parser = new ReceiptParser();
        $this->assertEqualsWithDelta(1946.57, $parser->extract($text)['gross_amount'], 0.001);
        $this->assertSame('Lekkerland SE', $parser->supplierNameGuess($text));
    }

    public function test_mt940_parser(): void
    {
        $mt940 = implode("\n", [
            ':20:STARTUMS',
            ':25:50010517/0648489890',
            ':28C:00001/001',
            ':60F:C260101EUR1000,00',
            ':61:2601020102D63,70NMSCNONREF',
            ':86:177?00SEPA-LASTSCHRIFT?20Blumen Lieferung?30GENODEF1XXX?31DE12500105170648489890?32HBW Sinsheim',
            ':62F:C260102EUR936,30',
        ]);

        $rows = (new Mt940Parser())->parse($mt940);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(-63.70, $rows[0]['amount'], 0.001);
        $this->assertSame('2026-01-02', $rows[0]['booking_date']);
        $this->assertSame('HBW Sinsheim', $rows[0]['counterparty']);
        $this->assertSame('Blumen Lieferung', $rows[0]['purpose']);
        $this->assertSame('DE12500105170648489890', $rows[0]['counterparty_iban']);
    }

    public function test_mt940_verwendungszweck_segmente_ohne_luecken(): void
    {
        // ?20–?29 sind 27-Zeichen-Segmente eines fortlaufenden Textes und
        // dürfen beim Zusammensetzen keine Wörter zerreißen.
        $mt940 = implode("\n", [
            ':20:STARTUMS',
            ':25:50010517/0648489890',
            ':28C:00001/001',
            ':60F:C260101EUR1000,00',
            ':61:2606010601D19,99NMSCNONREF',
            ':86:177?00Umbuchung?20Umbuchung PC Lautsprec?21her Expert Klein?32Stripe Technology Europe Lt?33d',
            ':62F:C260102EUR980,01',
        ]);

        $rows = (new Mt940Parser())->parse($mt940);
        $this->assertCount(1, $rows);
        $this->assertSame('Umbuchung PC Lautsprecher Expert Klein', $rows[0]['purpose']);
        $this->assertSame('Stripe Technology Europe Ltd', $rows[0]['counterparty']);
    }

    public function test_camt_parser(): void
    {
        $camt = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.02">
  <BkToCstmrStmt><Stmt>
    <Ntry>
      <Amt Ccy="EUR">63.70</Amt>
      <CdtDbtInd>DBIT</CdtDbtInd>
      <BookgDt><Dt>2026-01-02</Dt></BookgDt>
      <ValDt><Dt>2026-01-02</Dt></ValDt>
      <NtryDtls><TxDtls>
        <RmtInf><Ustrd>Blumen Lieferung</Ustrd></RmtInf>
        <RltdPties>
          <Cdtr><Nm>HBW Sinsheim</Nm></Cdtr>
          <CdtrAcct><Id><IBAN>DE12500105170648489890</IBAN></Id></CdtrAcct>
        </RltdPties>
      </TxDtls></NtryDtls>
    </Ntry>
  </Stmt></BkToCstmrStmt>
</Document>
XML;

        $rows = (new CamtParser())->parse($camt);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(-63.70, $rows[0]['amount'], 0.001);
        $this->assertSame('HBW Sinsheim', $rows[0]['counterparty']);
        $this->assertSame('DE12500105170648489890', $rows[0]['counterparty_iban']);
        $this->assertSame('Blumen Lieferung', $rows[0]['purpose']);
    }

    public function test_matching_engine_bewertet_passenden_beleg_hoch(): void
    {
        $account = $this->account();
        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "02.01.2026;HBW Sinsheim;Blumen;-63,70\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);
        $transaction = $account->bankTransactions()->first();

        $receipt = Receipt::create([
            'type' => 'incoming_invoice',
            'supplier_id' => $transaction->supplier_id,
            'gross_amount' => 63.70,
            'invoice_date' => '2026-01-03',
        ]);

        $score = (new MatchingEngine())->scoreReceipt($transaction, $receipt);
        $this->assertGreaterThanOrEqual(80, $score);

        $suggestions = (new MatchingEngine())->suggestReceipts($transaction);
        $this->assertTrue($suggestions->contains(fn ($s) => $s['receipt']->is($receipt)));
    }

    public function test_fints_fehler_werden_uebersetzt(): void
    {
        $this->assertSame(
            'Die PIN fehlt – bitte PIN eingeben.',
            FinTsErrorTranslator::translate('pin cannot be empty'),
        );
        $this->assertStringContainsString(
            'Verbindung zur Bank fehlgeschlagen',
            FinTsErrorTranslator::translate('cURL error 6: Could not resolve host'),
        );
        // Bereits deutsche Meldung bleibt unverändert.
        $this->assertSame('Eigene Meldung', FinTsErrorTranslator::translate('Eigene Meldung'));
    }

    public function test_receipt_parser_extrahiert_rechnungsdaten(): void
    {
        $text = "HBW Sinsheim GmbH\nRechnungsnummer: 2026-1001\nRechnungsdatum: 05.01.2026\n"
            . "Zwischensumme 53,53\nMwSt 19 % 10,17\nGesamtbetrag 63,70 EUR\n"
            . "IBAN DE12 5001 0517 0648 4898 90";

        $data = (new ReceiptParser())->extract($text);
        $this->assertSame('2026-1001', $data['invoice_number']);
        $this->assertSame('2026-01-05', $data['invoice_date']);
        $this->assertEqualsWithDelta(63.70, $data['gross_amount'], 0.001);
        $this->assertEqualsWithDelta(19.0, $data['tax_rate'], 0.001);
        $this->assertSame('DE12500105170648489890', $data['iban']);
    }

    public function test_receipt_parser_erkennt_faelligen_abrechnungsbetrag(): void
    {
        // Lotto-Wochenabrechnung: viele Zwischensummen, der fällige Betrag ist
        // mit "= 3.103,57" ausgewiesen und darf nicht durch den größten Betrag
        // (Gesamtumsatz 5.041,90) überstimmt werden.
        $text = "05.Gesamtumsatz 5.041,90\n14.Abrechnungsbetrag\n"
            . "Der Einzug erfolgt am 24.06.2026 über den dann fälligen Betrag (Euro) = 3.103,57";

        $data = (new ReceiptParser())->extract($text);
        $this->assertEqualsWithDelta(3103.57, $data['gross_amount'], 0.001);
    }
}
