<?php

namespace Tests\Feature;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Category;
use App\Services\Bank\BankImportService;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DedupeCrossSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bereinigt_csv_fints_doublette_und_behaelt_kontierten(): void
    {
        $this->seed(MasterDataSeeder::class);

        $account = BankAccount::create([
            'label' => 'Testkonto',
            'iban' => 'DE00100000000000000009',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $row = [
            'booking_date' => '2026-06-15',
            'counterparty' => 'Lekkerland SE',
            'purpose' => 'Warenlieferung 12345',
            'amount' => -119.00,
        ];

        // 1. CSV-Umsatz (ohne Referenz) anlegen und kontieren.
        (new BankImportService())->import($account, [$row], ImportSource::Csv, applyRules: false);
        $csv = BankTransaction::where('bank_account_id', $account->id)->firstOrFail();
        $csv->update(['category_id' => Category::firstOrCreate(['name' => 'Shop'], ['active' => true])->id]);

        // 2. Vorhandenen FinTS-Doppelgänger (mit Referenz) simulieren – so, wie er
        //    vor dem Dedup-Fix entstanden ist (direkt angelegt, nicht importiert).
        $fints = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-15',
            'counterparty' => 'Lekkerland SE',
            'purpose' => 'Warenlieferung 12345',
            'amount' => -119.00,
            'bank_reference' => 'E2E-987654',
            'dedup_hash' => BankTransaction::makeDedupHash($row + ['bank_reference' => 'E2E-987654']),
        ]);

        $this->assertSame(2, BankTransaction::where('bank_account_id', $account->id)->count());

        // Vorschau löscht nichts.
        $this->artisan('bank:dedupe-cross-source')->assertSuccessful();
        $this->assertSame(2, BankTransaction::where('bank_account_id', $account->id)->count());

        // --apply entfernt den unkontierten FinTS-Doppelgänger, behält den CSV-Umsatz.
        $this->artisan('bank:dedupe-cross-source', ['--apply' => true])->assertSuccessful();

        $active = BankTransaction::where('bank_account_id', $account->id)->get();
        $this->assertCount(1, $active);
        $this->assertSame($csv->id, $active->first()->id);
        $this->assertNotNull($active->first()->category_id);
        $this->assertSoftDeleted('bank_transactions', ['id' => $fints->id]);
    }
}
