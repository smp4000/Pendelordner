<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Business;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FintsIncrementalTest extends TestCase
{
    use RefreshDatabase;

    private function account(?string $lastFetched): BankAccount
    {
        $this->seed(DatabaseSeeder::class);

        return BankAccount::create([
            'label' => 'Testkonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
            'last_fetched_at' => $lastFetched,
        ]);
    }

    /** Ohne bisherigen Abruf gibt es kein Startdatum (Service nimmt Standardzeitraum). */
    public function test_ohne_letzten_abruf_null(): void
    {
        $this->assertNull($this->account(null)->fintsFetchFrom());
    }

    /** Mit letztem Abruf: Startdatum = letzter Abruf minus Überlappung (Tagesbeginn). */
    public function test_inkrementell_ab_letztem_abruf_minus_ueberlappung(): void
    {
        config()->set('pendelordner.fints.overlap_days', 7);
        $account = $this->account('2026-06-20 14:35:00');

        $from = $account->fintsFetchFrom();

        $this->assertInstanceOf(Carbon::class, $from);
        $this->assertSame('2026-06-13 00:00:00', $from->format('Y-m-d H:i:s'));
    }
}
