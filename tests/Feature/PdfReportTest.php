<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Models\BankAccount;
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
}
