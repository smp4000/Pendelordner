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
