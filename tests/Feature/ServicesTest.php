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

    /**
     * Derselbe Umsatz aus CSV (EREF im Zweck) und FinTS (EREF im Referenzfeld)
     * wird über die SEPA-Referenz als Dublette erkannt – trotz abweichendem
     * Empfänger-/Zweck-Text.
     */
    public function test_fints_dublette_ueber_eref_trotz_abweichendem_text(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        // CSV: EREF steht im Verwendungszweck, kein Referenzfeld.
        $csv = [
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL Aktiengesellschaft',
            'purpose' => '/ADV/0207702277 20260615 EREF: 0207702277 MREF: TP1',
            'amount' => -119.00,
        ];
        $log1 = $service->import($account, [$csv], ImportSource::Csv, applyRules: false);
        $this->assertSame(1, $log1->new_count);

        // FinTS: gleicher Umsatz, anderer Text, EREF im Referenzfeld.
        $fints = [
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL AG',
            'purpose' => '/ADV/0207702277 20260615 BIC: BN',
            'amount' => -119.00,
            'bank_reference' => '0207702277',
        ];
        $log2 = $service->import($account, [$fints], ImportSource::Fints, applyRules: false);
        $this->assertSame(0, $log2->new_count);
        $this->assertSame(1, $log2->duplicate_count);
        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
    }

    /**
     * Nach der Bereinigung (Doppelgänger soft-gelöscht) bringt ein erneuter
     * FinTS-Abruf den Umsatz NICHT zurück – der aktive Satz gewinnt.
     */
    public function test_reimport_stellt_bereinigten_doppelgaenger_nicht_wieder_her(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        // Aktiver CSV-Umsatz (EREF im Zweck).
        $csv = [
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL Aktiengesellschaft',
            'purpose' => '/ADV/0207702277 20260615 EREF: 0207702277 MREF: TP1',
            'amount' => -119.00,
        ];
        $service->import($account, [$csv], ImportSource::Csv, applyRules: false);

        // Soft-gelöschter FinTS-Doppelgänger (wie nach der Bereinigung).
        $fintsData = [
            'bank_account_id' => $account->id,
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL AG',
            'purpose' => '/ADV/0207702277 20260615 BIC: BN',
            'amount' => -119.00,
            'bank_reference' => '0207702277',
        ];
        $dupe = BankTransaction::create($fintsData + [
            'business_id' => $account->business_id,
            'dedup_hash' => BankTransaction::makeDedupHash($fintsData),
        ]);
        $dupe->delete();

        // Erneuter FinTS-Abruf -> Dublette am aktiven Satz, keine Wiederherstellung.
        $log = $service->import($account, [$fintsData], ImportSource::Fints, applyRules: false);

        $this->assertSame(0, $log->new_count); // 0 = weder neu noch wiederhergestellt
        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
        $this->assertSoftDeleted('bank_transactions', ['id' => $dupe->id]);
    }

    /**
     * Interne Umbuchung ohne Referenz: gleicher Zweck, aber unterschiedliche
     * Empfänger-Schreibweise/IBAN in CSV vs. FinTS -> dennoch Dublette.
     */
    public function test_referenzlose_umbuchung_ist_dublette_ueber_zweck(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        $csv = [
            'booking_date' => '2026-06-16', 'amount' => 753.91,
            'counterparty' => 'Christian Welle', 'counterparty_iban' => 'DE40530601800200250503',
            'purpose' => 'Ausgleich Argenturkonto',
        ];
        $service->import($account, [$csv], ImportSource::Csv, applyRules: false);

        $fints = [
            'booking_date' => '2026-06-16', 'amount' => 753.91,
            'counterparty' => 'Welle Christian', 'counterparty_iban' => 'DE75530601800500250503',
            'purpose' => 'Ausgleich Argenturkonto',
        ];
        $log = $service->import($account, [$fints], ImportSource::Fints, applyRules: false);

        $this->assertSame(0, $log->new_count);
        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
    }

    /**
     * FinTS führt die Referenz im Referenzfeld, CSV nur als „/ADV/<nr>" im Zweck
     * -> dennoch als Dublette erkannt.
     */
    public function test_dublette_ueber_adv_referenz(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        $csv = [
            'booking_date' => '2026-07-20', 'amount' => -2892.50,
            'counterparty' => 'CHRISTIAN WELLE',
            'purpose' => '/ADV/0207709430 20260720/0050360859 BIC: BN',
        ];
        $service->import($account, [$csv], ImportSource::Csv, applyRules: false);

        $fints = $csv;
        $fints['bank_reference'] = '0207709430';
        $log = $service->import($account, [$fints], ImportSource::Fints, applyRules: false);

        $this->assertSame(0, $log->new_count);
        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
    }

    /**
     * Referenzlose Umbuchung: CSV hängt SEPA-/TAN-Felder an den Zweck an, FinTS
     * nicht -> über den Kern-Zweck dennoch als Dublette erkannt.
     */
    public function test_dublette_trotz_zweck_anhang(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        $csv = [
            'booking_date' => '2026-07-18', 'amount' => -330.00,
            'counterparty' => 'Welle',
            'purpose' => 'WP 2026 Wohngeld Whg. 07-13 WEG Bertholdstr. 11 u. 13 07.2026 MREF: 106Mandat IBAN: DE00',
        ];
        $service->import($account, [$csv], ImportSource::Csv, applyRules: false);

        $fints = $csv;
        $fints['purpose'] = 'WP 2026 Wohngeld Whg. 07-13 WEG Bertholdstr. 11 u. 13 07.2026';
        $log = $service->import($account, [$fints], ImportSource::Fints, applyRules: false);

        $this->assertSame(0, $log->new_count);
        $this->assertSame(1, BankTransaction::where('bank_account_id', $account->id)->count());
    }

    /**
     * Zwei echt verschiedene Buchungen am selben Tag mit gleichem Betrag, aber
     * unterschiedlicher EREF, dürfen NICHT verschmolzen werden.
     */
    public function test_unterschiedliche_eref_bleibt_getrennt(): void
    {
        $account = $this->account();
        $service = new BankImportService();

        $rows = [
            ['booking_date' => '2026-06-17', 'amount' => -50.00, 'counterparty' => 'ARAL', 'purpose' => 'Sprit', 'bank_reference' => '111'],
            ['booking_date' => '2026-06-17', 'amount' => -50.00, 'counterparty' => 'ARAL', 'purpose' => 'Sprit', 'bank_reference' => '222'],
        ];
        $log = $service->import($account, $rows, ImportSource::Fints, applyRules: false);

        $this->assertSame(2, $log->new_count);
        $this->assertSame(2, BankTransaction::where('bank_account_id', $account->id)->count());
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

    public function test_regel_mit_zwei_kriterien_trennt_vertraege_derselben_gesellschaft(): void
    {
        $account = $this->account();
        $leben = Category::firstOrCreate(['name' => 'Lebensversicherung'], ['active' => true]);

        $make = fn (string $purpose) => BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-01',
            'counterparty' => 'AXA Life Europe',
            'purpose' => $purpose,
            'amount' => -136.08,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Zwei Verträge derselben Gesellschaft – gleicher Empfänger, andere Vertragsnummer.
        $vertragA = $make('Vertrag AL-9876085614 PrivRent InvestFlex');
        $vertragB = $make('Vertrag AL-1111111111 Risikoschutz');

        // Regel mit ZWEITEM Kriterium (UND): Empfänger AXA + Vertragsnummer im Zweck.
        $rule = MatchingRule::create([
            'pattern' => 'AXA Life Europe',
            'pattern_type' => 'counterparty',
            'pattern2' => 'AL-9876085614',
            'pattern_type2' => 'purpose',
            'category_id' => $leben->id,
            'priority' => 10,
            'active' => true,
        ]);

        $changed = (new MatchingEngine())->applyRuleToExisting($rule, onlyUnreviewed: true);

        // Nur der Vertrag mit passender Nummer wird zugeordnet, der andere nicht.
        $this->assertSame(1, $changed);
        $this->assertSame($leben->id, $vertragA->refresh()->category_id);
        $this->assertNull($vertragB->refresh()->category_id);
    }

    public function test_regel_kann_ueber_den_betrag_matchen(): void
    {
        $account = $this->account();
        $leben = Category::firstOrCreate(['name' => 'Lebensversicherung'], ['active' => true]);

        $make = fn (float $amount) => BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-01',
            'counterparty' => 'AXA Life Europe',
            'purpose' => 'Beitrag',
            'amount' => $amount,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Zwei Verträge, gleicher Empfänger, unterschiedlicher Betrag.
        $tx136 = $make(-136.08);
        $tx200 = $make(-200.00);

        // Regel: Empfänger AXA UND Betrag 136,08 (Vorzeichen egal, deutsches Format).
        $rule = MatchingRule::create([
            'pattern' => 'AXA Life Europe',
            'pattern_type' => 'counterparty',
            'pattern2' => '136,08',
            'pattern_type2' => 'amount',
            'category_id' => $leben->id,
            'priority' => 10,
            'active' => true,
        ]);

        $changed = (new MatchingEngine())->applyRuleToExisting($rule, onlyUnreviewed: true);

        $this->assertSame(1, $changed);
        $this->assertSame($leben->id, $tx136->refresh()->category_id);
        $this->assertNull($tx200->refresh()->category_id);
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

    public function test_parser_gesamtbetrag_statt_mwst_zeile(): void
    {
        // Hermes-Unternehmerabrechnung: Die MwSt-Zeile endet mit "EUR" und hat
        // 3 Beträge – sie darf NICHT als Zahlbetrag gelten (225,80 = nur MwSt).
        $text = "EUR 1.188,40Zwischensumme\n"
            . "Nettobetrag\t1.188,40EUR\n"
            . "MwSt 19,00 %  von: 1.188,40 EUR\t225,80EUR\n"
            . "Gesamtbetrag\t1.414,20EUR";

        $data = (new ReceiptParser())->extract($text);
        $this->assertEqualsWithDelta(1414.20, $data['gross_amount'], 0.001);
    }

    public function test_parser_verkaufsstellennummer_als_kundennummer(): void
    {
        // Lotto-Abrechnung: Verkaufsstellennummer = Kundennummer.
        $text = "WochentlicheAbrechnung\nVERKAUFSSTELLEN-\tLOTTO Hessen\n"
            . "14.Abrechnungsbetrag\n3.420,65\nVerkaufsstelle 12792\n";

        $data = (new ReceiptParser())->extract($text);
        $this->assertSame('12792', $data['customer_number']);
    }

    public function test_parser_label_block_layout_pvg(): void
    {
        // PVG-Layout: erst alle Labels (mit ":"), dann alle Werte in gleicher
        // Reihenfolge. Kundennummer und Rechnungsnummer stehen versetzt.
        $text = "Rechnungsdatum:\nRechnungsnummer:\nLeistungszeitraum:\nKundennummer:\nTour/FF:\n"
            . "31.05.2026\n0026152012\n25.05.2026 - 31.05.2026\n01/05667\n115/100\n"
            . "Zahlbetrag\t313,64 €";

        $data = (new ReceiptParser())->extract($text);
        $this->assertSame('0026152012', $data['invoice_number']);
        $this->assertSame('01/05667', $data['customer_number']);
        $this->assertEqualsWithDelta(313.64, $data['gross_amount'], 0.001);
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

    public function test_parser_erkennt_ust_aufteilung_gemischt(): void
    {
        // SB-Union-Rechnung mit gemischten Steuersätzen: der Steuer-Summenblock
        // weist je Satz Netto/USt/Brutto aus. OCR streut Wörter dazwischen.
        $text = "NettoBetrag:USt.-Betrag: Brutto\n"
            . "19%USt.: 382,11 72,60 Betrag: 454,71\n"
            . "7%USt.: 285,77 20,00 305,77\n"
            . "Summe: 667,88 92,60 760,48\n"
            . "Rechnungssumme EUR: 760,48";

        $rates = (new ReceiptParser())->taxBreakdown($text);

        $this->assertCount(2, $rates);
        // 19 % zuerst.
        $this->assertSame(19, $rates[0]['rate']);
        $this->assertEqualsWithDelta(454.71, $rates[0]['gross'], 0.001);
        $this->assertEqualsWithDelta(72.60, $rates[0]['tax'], 0.001);
        $this->assertSame(7, $rates[1]['rate']);
        $this->assertEqualsWithDelta(305.77, $rates[1]['gross'], 0.001);
        // Bruttosumme der Sätze = Rechnungssumme.
        $this->assertEqualsWithDelta(760.48, $rates[0]['gross'] + $rates[1]['gross'], 0.001);
    }

    public function test_avis_tabelle_parst_mehrere_zeilen_robust(): void
    {
        // Mehrseitiges Aral-Avis: breite Spalten (Layout), lange Belegart-Namen,
        // positive (OK/DK-Gutschrift) und negative (Soll) Zeilen. Der enge
        // Fenster-Ansatz scheiterte hier – der Tabellen-Parser muss alle treffen.
        $text = <<<TXT
        Belegnummer  Ihr Beleg    Belegart                          Datum        Betrag
        91114498     0991841329   *OK/DK-Tankstellen-Abrechnung      13.06.2026   9.166,99 EUR
        91119247     0991852674   *OK/DK-Tankstellen-Abrechnung      17.06.2026   9.848,58 EUR
        640272025    6312068751   *Stationskarten-Stundung           11.06.2026   -715,44 EUR
        651231736    0862975879   *Kreditkartenabrechnung            11.06.2026   -7.037,53 EUR
        651335657    0862999969   *Kreditkartenabrechnung            12.06.2026   -5.881,30 EUR
        TXT;

        $engine = new \App\Services\Matching\MatchingEngine();
        $table = $engine->parseAdviceTable($text);

        // Nach "Ihr Beleg" (Rechnungsnummer der Belege) – mit korrektem Vorzeichen.
        $this->assertEqualsWithDelta(9166.99, $table['0991841329'], 0.001);
        $this->assertEqualsWithDelta(9848.58, $table['0991852674'], 0.001);
        $this->assertEqualsWithDelta(-715.44, $table['6312068751'], 0.001);
        $this->assertEqualsWithDelta(-7037.53, $table['0862975879'], 0.001);
        $this->assertEqualsWithDelta(-5881.30, $table['0862999969'], 0.001);

        // Auch über die Belegnummer (Spalte 1) auffindbar.
        $this->assertEqualsWithDelta(9166.99, $table['91114498'], 0.001);

        // adviceAmountFor nutzt die Tabelle bevorzugt.
        $this->assertEqualsWithDelta(-7037.53, $engine->adviceAmountFor($text, '0862975879', $table), 0.001);
    }

    public function test_parser_gesamtsumme_englisches_format(): void
    {
        // Aral-Shop-Avis in englischem Zahlenformat: "1,005.03" (nicht 1,03),
        // mit Datums- und "0.00"-Spalten davor. Gesamt-Summe muss erkannt werden.
        $text = "Beleg          Ihr Beleg      Datum        Bruttobetrag\n"
            . "840003416      0227870140     17.05.2026   0.00              530.76 EUR\n"
            . "               Shop-Abrechnung 3322564785  17.05.26 LL SE\n"
            . "840021318      0227868387     16.05.2026   0.00              474.27 EUR\n"
            . "Gesamt-Summe:                 02.07.2026   1,005.03 EUR\n";

        $this->assertEqualsWithDelta(1005.03, (new ReceiptParser())->extract($text)['gross_amount'], 0.001);
    }

    public function test_avis_tabelle_englisches_format_und_fuehrende_null(): void
    {
        // Aral-Shop-Avis in ENGLISCHEM Zahlenformat (Punkt=Dezimal, Komma=Tausender),
        // mit "0.00"-Zwischenspalte vor dem Bruttobetrag und führender Null bei
        // "Ihr Beleg" (Avis: 0227869229, Beleg: 227869229).
        $text = "Beleg          Ihr Beleg      Datum                Bruttobetrag\n"
            . "840003926      0227869229     08.05.2026     0.00           6,403.09 EUR\n"
            . "               Shop-Abrechnung 2227618735  08.05.26 LL SE\n"
            . "840020326      0227870588     07.05.2026     0.00           196.57 EUR\n"
            . "840021106      0227868195     08.05.2026     0.00           4.17 EUR\n"
            . "Gesamt-Summe:                                22.06.2026     6,603.83 EUR\n";

        $engine = new \App\Services\Matching\MatchingEngine();
        $table = $engine->parseAdviceTable($text);

        // Englisches Format korrekt – NICHT die 0.00-Zwischenspalte.
        $this->assertEqualsWithDelta(6403.09, $table['0227869229'], 0.001);
        $this->assertEqualsWithDelta(196.57, $table['0227870588'], 0.001);
        $this->assertEqualsWithDelta(4.17, $table['0227868195'], 0.001);

        // Beleg OHNE führende Null findet den Betrag trotzdem.
        $this->assertEqualsWithDelta(6403.09, $engine->adviceAmountFor($text, '227869229', $table), 0.001);
        $this->assertEqualsWithDelta(196.57, $engine->adviceAmountFor($text, '227870588', $table), 0.001);
    }

    public function test_geldbetrag_deutsch_und_englisch(): void
    {
        $engine = new \App\Services\Matching\MatchingEngine();
        // Betrag steckt in einer Avis-Zeile; deutsches Format.
        $de = "770000000 0227869229 *Kredit 12.06.2026 6.403,09 EUR";
        $this->assertEqualsWithDelta(6403.09, $engine->parseAdviceTable($de)['0227869229'], 0.001);
        // Englisches Format.
        $en = "770000000 0227869229 *Kredit 12.06.2026 6,403.09 EUR";
        $this->assertEqualsWithDelta(6403.09, $engine->parseAdviceTable($en)['0227869229'], 0.001);
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
