<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\LedgerAccount;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplitTest extends TestCase
{
    use RefreshDatabase;

    public function test_aufteilung_auf_sachkonten_mit_brutto_und_netto(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create([
            'label' => 'Lotto Horas',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);
        $category = Category::firstOrCreate(['name' => 'Shop'], ['active' => true]);
        $la1 = LedgerAccount::create(['chart' => 'skr03', 'number' => '3790', 'name' => 'Einkauf Telefonkarten']);
        $la2 = LedgerAccount::create(['chart' => 'skr03', 'number' => '3793', 'name' => 'e-Loading']);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'category_id' => $category->id,
            'booking_date' => '2026-06-03',
            'counterparty' => 'Lekkerland SE',
            'amount' => -119.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Brutto-Modus: Beträge werden 1:1 als Brutto gespeichert.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splitMode', 'brutto')
            ->set('splits', [
                ['ledger_account_id' => $la1->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '19', 'amount' => '100,00'],
                ['ledger_account_id' => $la2->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '0', 'amount' => '19,00'],
            ])
            ->call('saveSplits');

        $this->assertSame(2, $tx->accountAssignments()->count());
        $a = $tx->accountAssignments()->where('ledger_account_id', $la1->id)->first();
        $this->assertEqualsWithDelta(100.00, (float) $a->amount, 0.001);
        $this->assertEqualsWithDelta(19.00, (float) $a->tax_rate, 0.001);
        // Kategorie wird vom Umsatz übernommen (nicht je Zeile erfasst).
        $this->assertSame($category->id, $a->category_id);

        // Netto-Modus: 100 netto bei 19 % -> 119,00 brutto gespeichert.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splitMode', 'netto')
            ->set('splits', [
                ['ledger_account_id' => $la1->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '19', 'amount' => '100,00'],
            ])
            ->call('saveSplits');

        $this->assertSame(1, $tx->accountAssignments()->count());
        $this->assertEqualsWithDelta(119.00, (float) $tx->accountAssignments()->first()->amount, 0.001);
    }

    public function test_zugeordnete_belege_lassen_sich_sortieren(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'booking_date' => '2026-06-01',
            'amount' => -30.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        $r1 = Receipt::create(['type' => 'incoming_invoice', 'gross_amount' => 10]);
        $r2 = Receipt::create(['type' => 'incoming_invoice', 'gross_amount' => 20]);
        $tx->receipts()->attach($r1->id, ['amount' => 10, 'sort_order' => 0]);
        $tx->receipts()->attach($r2->id, ['amount' => 20, 'sort_order' => 1]);

        $this->assertSame([$r1->id, $r2->id], $tx->receipts->pluck('id')->all());

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('moveReceipt', $r1->id, 'down');

        $this->assertSame([$r2->id, $r1->id], $tx->fresh()->receipts->pluck('id')->all());
    }
}
