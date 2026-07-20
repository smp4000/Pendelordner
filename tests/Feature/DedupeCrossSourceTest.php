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

        // 1. CSV-Umsatz (EREF im Zweck) anlegen und kontieren.
        $csvRow = [
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL Aktiengesellschaft',
            'purpose' => '/ADV/0207702277 20260615 EREF: 0207702277 MREF: TP1',
            'amount' => -119.00,
        ];
        (new BankImportService())->import($account, [$csvRow], ImportSource::Csv, applyRules: false);
        $csv = BankTransaction::where('bank_account_id', $account->id)->firstOrFail();
        $csv->update(['category_id' => Category::firstOrCreate(['name' => 'Shop'], ['active' => true])->id]);

        // 2. Vorhandenen FinTS-Doppelgänger (EREF im Referenzfeld, anderer Text)
        //    simulieren – so, wie er vor dem Dedup-Fix entstanden ist.
        $fintsData = [
            'bank_account_id' => $account->id,
            'booking_date' => '2026-06-15',
            'counterparty' => 'ARAL AG',
            'purpose' => '/ADV/0207702277 20260615 BIC: BN',
            'amount' => -119.00,
            'bank_reference' => '0207702277',
        ];
        $fints = BankTransaction::create($fintsData + [
            'business_id' => $account->business_id,
            'dedup_hash' => BankTransaction::makeDedupHash($fintsData),
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
