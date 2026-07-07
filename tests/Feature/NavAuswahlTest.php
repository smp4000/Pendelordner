<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NavAuswahlTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_nur_durch_ausgewaehlte_umsaetze(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $mk = fn (string $day) => BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => $day, 'counterparty' => 'ARAL', 'amount' => -100,
            'reviewed' => true, 'split_open' => true, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        $t1 = $mk('2026-06-01');
        $t2 = $mk('2026-06-02');
        $t3 = $mk('2026-06-03');
        $t4 = $mk('2026-06-04');
        $t5 = $mk('2026-06-05');

        // Nur t2, t3, t4 ausgewählt.
        $comp = Livewire::test(Kontoumsatzdetails::class)
            ->set('navIds', [$t2->id, $t3->id, $t4->id])
            ->set('selectedTransactionId', $t2->id);

        // Zähler: 3 gesamt, Position 1 (obwohl 5 Umsätze existieren).
        $this->assertSame(3, $comp->instance()->total);
        $this->assertSame(1, $comp->instance()->position);

        // Weiterblättern bleibt in der Auswahl.
        $comp->call('goTo', 'next');
        $comp->assertSet('selectedTransactionId', $t3->id);

        $comp->call('goTo', 'last');
        $comp->assertSet('selectedTransactionId', $t4->id); // nicht t5!

        $comp->call('goTo', 'next'); // am Ende -> bleibt bei t4
        $comp->assertSet('selectedTransactionId', $t4->id);

        $comp->call('goTo', 'first');
        $comp->assertSet('selectedTransactionId', $t2->id); // nicht t1!
    }
}
