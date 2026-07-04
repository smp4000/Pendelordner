<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Filament\Widgets\OffeneHinweiseWidget;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffeneHinweiseTest extends TestCase
{
    use RefreshDatabase;

    private function tx(array $attributes = []): BankTransaction
    {
        $account = BankAccount::firstOrCreate(
            ['label' => 'Testkonto'],
            ['business_id' => Business::first()->id, 'currency' => 'EUR']
        );

        return BankTransaction::create(array_merge([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-01', 'counterparty' => 'ARAL AG',
            'amount' => -1018.07, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ], $attributes));
    }

    public function test_hinweis_als_offen_gespeichert_erscheint_und_wird_erledigt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx();

        // Hinweis mit "erfordert Reaktion" speichern.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('accountantNote', 'Gutschrift angefordert, 18 Rollcontainer zu viel')
            ->set('noteOpen', true)
            ->call('saveNote');

        $tx->refresh();
        $this->assertTrue($tx->note_open);
        $this->assertSame(1, BankTransaction::query()->openNote()->count());

        // Widget wird eingeblendet und zeigt den Umsatz.
        $this->assertTrue(OffeneHinweiseWidget::canView());
        Livewire::test(OffeneHinweiseWidget::class)
            ->assertCanSeeTableRecords([$tx])
            ->callTableAction('erledigt', $tx);

        // Nach "Erledigt": Hinweistext bleibt, aber nicht mehr offen.
        $tx->refresh();
        $this->assertFalse($tx->note_open);
        $this->assertNotNull($tx->accountant_note);
        $this->assertSame(0, BankTransaction::query()->openNote()->count());
        $this->assertFalse(OffeneHinweiseWidget::canView());
    }

    public function test_ohne_hinweistext_kein_offener_hinweis(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx();

        // "erfordert Reaktion" angehakt, aber kein Text -> kein offener Hinweis.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('accountantNote', '')
            ->set('noteOpen', true)
            ->call('saveNote');

        $this->assertFalse($tx->refresh()->note_open);
        $this->assertSame(0, BankTransaction::query()->openNote()->count());
    }
}
