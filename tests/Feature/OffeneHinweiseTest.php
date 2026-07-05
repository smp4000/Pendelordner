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

    public function test_offener_hinweis_landet_in_der_glocke_und_verschwindet_beim_erledigen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx();

        // Hinweis als offen speichern -> Glocken-Benachrichtigung für den Nutzer.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('accountantNote', 'Gutschrift angefordert')
            ->set('noteOpen', true)
            ->call('saveNote');

        $this->assertSame(1, $user->notifications()->count());
        $this->assertStringContainsString('Gutschrift angefordert', $user->notifications()->first()->data['body'] ?? '');

        // Über das Dashboard-Widget erledigen -> Glocken-Meldung wird entfernt.
        Livewire::test(OffeneHinweiseWidget::class)->callTableAction('erledigt', $tx);

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_sync_befehl_uebernimmt_bestehende_offene_hinweise_in_die_glocke(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Offener Hinweis, der VOR der Glocken-Funktion angelegt wurde (kein Glocken-Eintrag).
        $this->tx(['note_open' => true, 'accountant_note' => 'Gutschrift angefordert']);
        $this->assertSame(0, $user->notifications()->count());

        $this->artisan('hinweise:sync-glocke')->assertSuccessful();

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    /**
     * Filaments DatabaseNotification ist ShouldQueue. Ohne laufenden Worker
     * (Shared Hosting, QUEUE_CONNECTION=database) muss die Glocken-Meldung
     * trotzdem sofort geschrieben werden (notifyNow) – sonst bleibt die Glocke
     * leer, obwohl der Befehl "erledigt" meldet.
     */
    public function test_glocke_wird_ohne_queue_worker_gefuellt(): void
    {
        config(['queue.default' => 'database']);
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tx = $this->tx(['note_open' => true, 'accountant_note' => 'Bitte prüfen']);
        \App\Support\OffeneHinweisGlocke::sync($tx->refresh());

        // Meldung liegt sofort in notifications (nicht nur als Job in der Queue).
        $this->assertGreaterThanOrEqual(1, $user->fresh()->notifications()->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('jobs')->count());
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
