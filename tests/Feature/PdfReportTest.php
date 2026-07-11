<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use App\Models\SteuerDocument;
use App\Services\Bank\BankImportService;
use App\Services\Bank\Parsers\CsvBankParser;
use App\Services\Pdf\PdfReportService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bericht_reihenfolge_wie_kontoauszug(): void
    {
        Storage::fake('belege');
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        // Bank-Export/CSV: je Buchungstag die ZULETZT gebuchten Umsätze zuerst
        // (auch mit späterem Wertdatum). Der Kontoauszug zeigt sie umgekehrt –
        // zuerst gebucht zuerst. Deshalb sortiert der Bericht id absteigend.
        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Valutadatum;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "15.06.2026;15.06.2026;Firma C;x;-1,00\n"   // zuletzt gebucht
            . "15.06.2026;14.06.2026;Firma B;x;-3,00\n"
            . "15.06.2026;13.06.2026;Firma A;x;-2,00\n"   // zuerst gebucht
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        // Dieselbe Sortierung wie im Bericht (PdfReportService): Buchungstag
        // aufsteigend, dann umgekehrte Import-Reihenfolge.
        $order = BankTransaction::where('bank_account_id', $account->id)
            ->orderBy('booking_date')
            ->orderByDesc('id')
            ->pluck('counterparty')->all();

        // Wie im Kontoauszug: zuerst gebucht (Firma A) zuerst.
        $this->assertSame(['Firma A', 'Firma B', 'Firma C'], $order);
    }

    public function test_kontoauszug_wird_im_bericht_vorangestellt(): void
    {
        Storage::fake('belege');
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n08.06.2026;X;y;-10,00\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        // Kontoauszug-Dokument (echtes PDF, Druck an) -> kommt zwischen die
        // beiden Berichtsseiten (Deckblatt/Zusammenfassung) und die Umsatzliste.
        Storage::disk('belege')->put('2026/06/ka.pdf', DomPdf::loadHTML('<h1>Original Kontoauszug</h1>')->output());
        SteuerDocument::create([
            'bank_account_id' => $account->id, 'period' => '2026-06-01', 'category' => 'Kontoauszug',
            'file_path' => '2026/06/ka.pdf', 'mime_type' => 'application/pdf',
            'file_name' => 'Kontoauszug.pdf', 'include_in_report' => true, 'sort_order' => 1,
        ]);

        $service = new PdfReportService();
        $from = Carbon::parse('2026-06-01');
        $to = Carbon::parse('2026-06-30');

        $full = $service->generate($from, $to, null, $account);
        $overview = $service->generate($from, $to, null, $account, withReceipts: false);

        Storage::disk('local')->assertExists($full);
        // Der volle Bericht (mit vorangestelltem Kontoauszug) ist größer als die
        // reine Übersicht – die Kontoauszug-Seite ist also enthalten.
        $this->assertGreaterThan(
            strlen(Storage::disk('local')->get($overview)),
            strlen(Storage::disk('local')->get($full)),
        );
    }

    public function test_steuerberater_bericht_wird_erzeugt_und_belege_angehaengt(): void
    {
        Storage::fake('belege');
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);

        $account = BankAccount::create([
            'label' => 'Testkonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "08.01.2026;HBW Sinsheim;Blumen;-63,70\n"
            . "09.01.2026;Telekom Deutschland GmbH;Telefon;-89,95\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        // PDF-Beleg erzeugen und an den ersten Umsatz hängen
        $pdfContent = DomPdf::loadHTML('<h1>Rechnung HBW Sinsheim</h1><p>Blumen 63,70 EUR</p>')->output();
        Storage::disk('belege')->put('2026/01/rechnung-hbw.pdf', $pdfContent);

        $receipt = Receipt::create([
            'type' => 'incoming_invoice',
            'invoice_number' => '2026-1001',
            'gross_amount' => 63.70,
            'invoice_date' => '2026-01-07',
            'file_path' => '2026/01/rechnung-hbw.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $transaction = $account->bankTransactions()->where('counterparty', 'HBW Sinsheim')->first();
        $transaction->receipts()->attach($receipt->id, ['amount' => 63.70]);
        // Status setzen, damit die gedrehten Stempel (BEZAHLT/GEBUCHT) gezeichnet werden.
        $transaction->update(['fully_paid' => true, 'reviewed' => true]);

        $path = (new PdfReportService())->generateMonthlyReport(Carbon::parse('2026-01-15'));

        Storage::disk('local')->assertExists($path);
        $content = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF', $content);
        // Vorspann (>=3 Seiten) + Trennseite + 1 Belegseite -> Dokument nicht trivial klein
        $this->assertGreaterThan(3000, strlen($content));

        // Mit Beleg angehängt = ein deutlich größeres Dokument als ohne.
        $withReceipt = strlen($content);

        // Beleg aus dem Bericht ausschließen -> Datei wird nicht mehr angehängt.
        $receipt->update(['include_in_report' => false]);
        $path2 = (new PdfReportService())->generateMonthlyReport(Carbon::parse('2026-01-15'));
        $withoutReceipt = strlen(Storage::disk('local')->get($path2));

        $this->assertLessThan($withReceipt, $withoutReceipt);
    }

    public function test_zip_sicherung_enthaelt_auch_nicht_ausgedruckte_belege(): void
    {
        Storage::fake('belege');
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);

        $account = BankAccount::create(['label' => 'Testkonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "08.01.2026;ARAL AG;Sammellastschrift;-1018,07\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);
        $transaction = $account->bankTransactions()->first();

        // Zwei zugeordnete Belege: einer im Bericht, einer NICHT.
        Storage::disk('belege')->put('2026/01/gedruckt.pdf', '%PDF gedruckt');
        Storage::disk('belege')->put('2026/01/nur-sicherung.pdf', '%PDF sicherung');

        $gedruckt = Receipt::create([
            'type' => 'incoming_invoice', 'invoice_number' => 'RG-PRINT', 'gross_amount' => 500,
            'file_path' => '2026/01/gedruckt.pdf', 'mime_type' => 'application/pdf', 'include_in_report' => true,
        ]);
        $nurSicherung = Receipt::create([
            'type' => 'incoming_invoice', 'invoice_number' => 'RG-BACKUP', 'gross_amount' => 518.07,
            'file_path' => '2026/01/nur-sicherung.pdf', 'mime_type' => 'application/pdf', 'include_in_report' => false,
        ]);
        $transaction->receipts()->attach([$gedruckt->id => ['amount' => 500], $nurSicherung->id => ['amount' => 518.07]]);

        $service = new PdfReportService();
        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-01-31');

        // Gedruckte Anhänge: nur der Bericht-Beleg.
        $printed = collect($service->attachmentFiles($from, $to, null, $account))->pluck('absolute');
        $this->assertTrue($printed->contains(fn ($p) => str_contains($p, 'gedruckt.pdf')));
        $this->assertFalse($printed->contains(fn ($p) => str_contains($p, 'nur-sicherung.pdf')));

        // Sicherung: enthält den NICHT ausgedruckten Beleg, nicht aber den bereits gedruckten.
        $backup = collect($service->unprintedReceiptFiles($from, $to, null, $account));
        $this->assertTrue($backup->pluck('absolute')->contains(fn ($p) => str_contains($p, 'nur-sicherung.pdf')));
        $this->assertFalse($backup->pluck('absolute')->contains(fn ($p) => str_contains($p, 'gedruckt.pdf')));
        // Sprechender Dateiname mit Datum, Empfänger und Rechnungsnummer.
        $this->assertStringContainsString('ARAL', $backup->first()['name']);
        $this->assertStringContainsString('RG-BACKUP', $backup->first()['name']);
    }

    public function test_steuerbuero_datei_wird_angehaengt(): void
    {
        Storage::fake('belege');
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);

        $account = BankAccount::create(['label' => 'Testkonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "08.01.2026;HBW Sinsheim;Blumen;-63,70\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        $receipt = Receipt::create([
            'type' => 'incoming_invoice', 'gross_amount' => 63.70, 'invoice_date' => '2026-01-07',
            'file_path' => '2026/01/beleg.pdf', 'mime_type' => 'application/pdf',
        ]);
        Storage::disk('belege')->put('2026/01/beleg.pdf', DomPdf::loadHTML('<h1>Beleg</h1>')->output());
        $account->bankTransactions()->where('counterparty', 'HBW Sinsheim')->first()
            ->receipts()->attach($receipt->id, ['amount' => 63.70]);

        // Steuerbüro-Datei desselben Monats.
        Storage::disk('belege')->put('2026/01/monatsrechnung.pdf', DomPdf::loadHTML('<h1>Monatsrechnung DATEV</h1>')->output());
        SteuerDocument::create([
            'bank_account_id' => $account->id, 'period' => '2026-01-01', 'category' => 'Monatsrechnung',
            'file_path' => '2026/01/monatsrechnung.pdf', 'mime_type' => 'application/pdf', 'sort_order' => 1,
        ]);

        $path = (new PdfReportService())->generate(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), null, $account);
        $withDoc = strlen(Storage::disk('local')->get($path));

        // Auf "nicht drucken" gesetzt -> Datei wird nicht eingebettet (Dokument kleiner).
        SteuerDocument::query()->update(['include_in_report' => false]);
        $path2 = (new PdfReportService())->generate(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'), null, $account);
        $withNoPrint = strlen(Storage::disk('local')->get($path2));

        $this->assertGreaterThan($withNoPrint, $withDoc);
    }

    public function test_uebersicht_ohne_belege_und_einzelbelege_mit_monatsnummern(): void
    {
        Storage::fake('belege');
        Storage::fake('local');
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);

        $account = BankAccount::create(['label' => 'Testkonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "08.01.2026;HBW Sinsheim;Blumen;-63,70\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        // Steuerbüro-Datei (wird Nr. 1) + Kontoauszug-Beleg (wird Nr. 2) im Januar.
        Storage::disk('belege')->put('2026/01/mr.pdf', DomPdf::loadHTML('<h1>Monatsrechnung</h1>')->output());
        SteuerDocument::create([
            'bank_account_id' => $account->id, 'period' => '2026-01-01', 'category' => 'Monatsrechnung',
            'file_path' => '2026/01/mr.pdf', 'mime_type' => 'application/pdf', 'sort_order' => 1,
        ]);
        Storage::disk('belege')->put('2026/01/beleg.pdf', DomPdf::loadHTML('<h1>Beleg</h1>')->output());
        $receipt = Receipt::create([
            'type' => 'incoming_invoice', 'gross_amount' => 63.70,
            'file_path' => '2026/01/beleg.pdf', 'mime_type' => 'application/pdf',
        ]);
        $account->bankTransactions()->first()->receipts()->attach($receipt->id, ['amount' => 63.70]);

        $service = new PdfReportService();
        $from = Carbon::parse('2026-01-01');
        $to = Carbon::parse('2026-01-31');

        // Einzel-Belege: Jahr-Monat-Nummer + eingegebene Kategorie/Bezeichnung,
        // Steuerbüro-Datei zuerst (Beleg ohne Rechnungsnr./Lieferant -> ohne Zusatz).
        $files = $service->attachmentFiles($from, $to, null, $account);
        $this->assertSame(['2026-01-01_Monatsrechnung.pdf', '2026-01-02.pdf'], array_column($files, 'name'));
        foreach ($files as $f) {
            $this->assertFileExists($f['absolute']);
        }

        // Übersicht (ohne Belege) ist kleiner als der Komplett-Bericht.
        $overview = $service->generate($from, $to, null, $account, withReceipts: false);
        $full = $service->generate($from, $to, null, $account);
        $this->assertStringContainsString('Uebersicht_', $overview);
        $this->assertLessThan(
            strlen(Storage::disk('local')->get($full)),
            strlen(Storage::disk('local')->get($overview)),
        );
    }
}
