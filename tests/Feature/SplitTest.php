<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_aufteilung_speichert_kontierungspositionen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create([
            'label' => 'Lotto Horas',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-03',
            'counterparty' => 'LOTTO Hessen',
            'amount' => -100.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $kosten = Category::firstOrCreate(['name' => 'Lotto Kosten'], ['active' => true]);
        $provision = Category::firstOrCreate(['name' => 'Lotto Provision'], ['active' => true]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splits', [
                ['category_id' => $kosten->id, 'amount' => '80,00', 'booking_text' => 'Kosten'],
                ['category_id' => $provision->id, 'amount' => '20,00', 'booking_text' => ''],
            ])
            ->call('saveSplits');

        $this->assertSame(2, $tx->accountAssignments()->count());
        $a = $tx->accountAssignments()->where('category_id', $kosten->id)->first();
        $this->assertEqualsWithDelta(80.00, (float) $a->amount, 0.001);

        // Speichern ersetzt vorhandene Positionen (kein Duplikat-Aufbau)
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splits', [
                ['category_id' => $kosten->id, 'amount' => '100,00', 'booking_text' => ''],
            ])
            ->call('saveSplits');

        $this->assertSame(1, $tx->accountAssignments()->count());
    }
}
