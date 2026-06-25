<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\Business;
use App\Services\Accounting\DatevExportService;
use App\Services\Accounting\KontierungService;
use App\Services\Bank\BankImportService;
use App\Services\Bank\Parsers\CsvBankParser;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\SupplierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->seed(SupplierSeeder::class);
    }

    private function importedTransaction()
    {
        $account = BankAccount::create([
            'label' => 'Testkonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $rows = (new CsvBankParser())->parse(
            "Buchungstag;Name Zahlungsbeteiligter;Verwendungszweck;Betrag\n"
            . "08.01.2026;HBW Sinsheim;Blumen;-63,70\n"
        );
        (new BankImportService())->import($account, $rows, ImportSource::Csv);

        return $account->bankTransactions()->first();
    }

    public function test_kontierung_leitet_konto_aus_kategorie_ab(): void
    {
        $transaction = $this->importedTransaction();

        // Auto-Vorkontierung hat HBW -> Blumen gesetzt
        $this->assertSame('Blumen', $transaction->category->name);

        $assignment = (new KontierungService())->bookTransaction($transaction);

        $this->assertNotNull($assignment);
        $this->assertSame('3300', $assignment->account);        // SKR03 Blumen (7%)
        $this->assertSame('1200', $assignment->contra_account); // SKR03 Bank
        $this->assertSame('8', $assignment->tax_key);           // 7% Vorsteuer
        $this->assertEqualsWithDelta(63.70, (float) $assignment->amount, 0.001);
    }

    public function test_datev_export_erzeugt_extf_datei(): void
    {
        Storage::fake('local');

        $transaction = $this->importedTransaction();
        (new KontierungService())->bookTransaction($transaction);

        $export = (new DatevExportService())->generate(
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-31'),
            null,
            null,
            '12345',
            '67890',
        );

        Storage::disk('local')->assertExists($export->file_path);
        $this->assertSame(1, $export->entry_count);

        $content = mb_convert_encoding(Storage::disk('local')->get($export->file_path), 'UTF-8', 'Windows-1252');
        $this->assertStringContainsString('EXTF', $content);
        $this->assertStringContainsString('Buchungsstapel', $content);
        $this->assertStringContainsString('63,70', $content);
        $this->assertStringContainsString('3300', $content);   // Konto
        $this->assertStringContainsString('"S"', $content);    // Soll (Ausgabe)
    }
}
