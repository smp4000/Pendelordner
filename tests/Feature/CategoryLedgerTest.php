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

    private function bootPanel(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /**
     * Die Kategorie liefert ihr SKR03/04-Konto für die Steuerberater-Anzeige
     * (categorySkr) – das operative Sachkonto (edtas) bleibt davon unberührt.
     */
    public function test_kategorie_zeigt_skr03_und_skr04_konto(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'skr03', 'number' => '4360'], ['name' => 'Versicherungen']);
        LedgerAccount::firstOrCreate(['chart' => 'skr04', 'number' => '6400'], ['name' => 'Versicherungen']);
        $category = Category::firstOrCreate(
            ['name' => 'Versicherung'],
            ['active' => true, 'skr03_account' => '4360', 'skr04_account' => '6400'],
        );

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('assignCategoryId', $category->id);

        $skr = $component->instance()->categorySkr;
        $this->assertSame('4360', $skr['skr03']?->number);
        $this->assertSame('6400', $skr['skr04']?->number);
    }

    /**
     * Das Wählen einer Kategorie überschreibt NICHT das operative Sachkonto
     * (edtas). Beides ist getrennt: Konto = operativ, Kategorie = SKR (Steuerbüro).
     */
    public function test_kategorie_laesst_operatives_konto_unberuehrt(): void
    {
        $this->bootPanel();

        $account = BankAccount::create([
            'label' => 'Geschäftskonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);
        $edtas = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4800'], ['name' => 'Planmäßige Abschreibung']);
        $category = Category::firstOrCreate(
            ['name' => 'Versicherung'],
            ['active' => true, 'skr03_account' => '4360', 'skr04_account' => '6400'],
        );

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id,
            'business_id' => $account->business_id,
            'ledger_account_id' => $edtas->id,
            'booking_date' => '2026-06-26',
            'counterparty' => 'BKK Linde',
            'amount' => -1033.44,
            'reviewed' => false,
            'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('assignCategoryId', $category->id);

        $tx->refresh();
        $this->assertSame($category->id, $tx->category_id);
        // Operatives edtas-Konto unverändert.
        $this->assertSame($edtas->id, $tx->ledger_account_id);
    }

    /** Die operative Sachkonto-Suche blendet SKR03/04 aus (nur edtas/gastro/kfz). */
    public function test_sachkonto_suche_ohne_skr(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4800'], ['name' => 'Planmäßige Abschreibung auf Anlagevermögen']);
        LedgerAccount::firstOrCreate(['chart' => 'skr03', 'number' => '4800'], ['name' => 'Reparaturen und Instandhaltungen']);

        $results = Livewire::test(Kontoumsatzdetails::class)
            ->set('ledgerSearch', '4800')
            ->instance()->ledgerResults;

        $this->assertTrue($results->isNotEmpty());
        $this->assertEmpty($results->where('chart', 'skr03')->all(), 'SKR03 darf in der operativen Konto-Suche nicht erscheinen.');
        $this->assertNotEmpty($results->where('chart', 'edtas')->all());
    }
}
