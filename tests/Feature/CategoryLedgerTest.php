<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Category;
use App\Models\LedgerAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Beim Wählen einer Kategorie wird das hinterlegte Sachkonto des
     * Standard-Kontenrahmens (SKR03) automatisch auf den Umsatz gebucht –
     * Grundlage für die Auswertung beim Steuerberater.
     */
    public function test_kategorie_setzt_skr03_sachkonto_am_umsatz(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create([
            'label' => 'Geschäftskonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $ledger = LedgerAccount::firstOrCreate(
            ['chart' => 'skr03', 'number' => '4500'],
            ['name' => 'Fahrzeugkosten'],
        );
        $category = Category::firstOrCreate(
            ['name' => 'Fahrzeuge'],
            ['active' => true, 'skr03_account' => '4500', 'skr04_account' => '6500'],
        );

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'booking_date' => '2026-06-03',
            'counterparty' => 'Tankstelle',
            'amount' => -80.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('assignCategoryId', $category->id);

        $tx->refresh();
        $this->assertSame($category->id, $tx->category_id);
        $this->assertSame($ledger->id, $tx->ledger_account_id);
    }

    /** Kategorie ohne Kontierung (z. B. Lotto) lässt ein bestehendes Konto unangetastet. */
    public function test_kategorie_ohne_konto_aendert_sachkonto_nicht(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create([
            'label' => 'Geschäftskonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $ledger = LedgerAccount::firstOrCreate(['chart' => 'skr03', 'number' => '4980'], ['name' => 'Sonstiger Betriebsbedarf']);
        $lotto = Category::firstOrCreate(['name' => 'Lotto'], ['active' => true, 'skr03_account' => null, 'skr04_account' => null]);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'ledger_account_id' => $ledger->id,
            'booking_date' => '2026-06-03',
            'counterparty' => 'Lotto Toto',
            'amount' => -50.00,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('assignCategoryId', $lotto->id);

        $tx->refresh();
        $this->assertSame($lotto->id, $tx->category_id);
        // Bestehendes Konto bleibt erhalten, da die Kategorie kein Konto vorgibt.
        $this->assertSame($ledger->id, $tx->ledger_account_id);
    }
}
