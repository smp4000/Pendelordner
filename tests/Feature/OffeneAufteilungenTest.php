<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Filament\Widgets\OffeneAufteilungenWidget;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\LedgerAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffeneAufteilungenTest extends TestCase
{
    use RefreshDatabase;

    private function tx(): BankTransaction
    {
        $account = BankAccount::firstOrCreate(
            ['label' => 'Testkonto'],
            ['business_id' => Business::first()->id, 'currency' => 'EUR']
        );

        return BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-16', 'counterparty' => 'Lekkerland SE',
            'amount' => -100.00, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
    }

    public function test_merker_erscheint_und_wird_durch_vollstaendige_aufteilung_erledigt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx();
        $la = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3070'], ['name' => 'Einkauf']);

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id);

        // Als "Aufteilung offen" merken (Umsatz kann trotzdem geprüft in den Bericht).
        $component->call('toggleSplitOpen');
        $this->assertTrue($tx->refresh()->split_open);
        $this->assertTrue(OffeneAufteilungenWidget::canView());
        Livewire::test(OffeneAufteilungenWidget::class)->assertCanSeeTableRecords([$tx]);

        // Rest offen = voller Betrag, solange nichts aufgeteilt ist.
        $this->assertEqualsWithDelta(100.00, $tx->refresh()->split_remaining, 0.001);

        // Aufteilung vollständig ergänzen -> Merker wird automatisch entfernt.
        $component->call('toggleSplit')
            ->call('setSplitMode', 'brutto')
            ->call('setSplitLedger', 0, $la->id)
            ->set('splits.0.amount', '100,00');

        $this->assertFalse($tx->refresh()->split_open, 'Merker muss bei Rest 0 automatisch weg sein.');
        $this->assertFalse(OffeneAufteilungenWidget::canView());
    }

    public function test_merker_kann_im_widget_manuell_erledigt_werden(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx();
        $tx->update(['split_open' => true]);

        Livewire::test(OffeneAufteilungenWidget::class)
            ->assertCanSeeTableRecords([$tx])
            ->callTableAction('erledigt', $tx);

        $this->assertFalse($tx->refresh()->split_open);
    }
}
