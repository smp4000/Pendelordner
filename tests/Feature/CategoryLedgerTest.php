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

    /** Die Kategorie liefert ihr eDTAS-Konto für die Steuerberater-Anzeige. */
    public function test_kategorie_zeigt_edtas_konto(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4360'], ['name' => 'Versicherungen']);
        $category = Category::firstOrCreate(
            ['name' => 'Versicherung'],
            ['active' => true, 'edtas_account' => '4360'],
        );

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('assignCategoryId', $category->id);

        $edtas = $component->instance()->categoryLedger;
        $this->assertSame('4360', $edtas['number']);
        $this->assertSame('Versicherungen', $edtas['name']);
    }

    /** Das Wählen einer Kategorie überschreibt NICHT das operative Sachkonto. */
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
            ['active' => true, 'edtas_account' => '4360'],
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
        $this->assertSame($edtas->id, $tx->ledger_account_id);
    }

    /** Die Kategorie-Suche findet per Name UND per eDTAS-Konto (Nummer/Bezeichnung). */
    public function test_kategorie_suche_per_name_und_edtas(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4360'], ['name' => 'Versicherungen']);
        $category = Category::firstOrCreate(
            ['name' => 'Versicherung'],
            ['active' => true, 'edtas_account' => '4360'],
        );

        $byName = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'Versich')->instance()->categoryResults;
        $this->assertTrue($byName->contains('id', $category->id));

        $byNumber = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '4360')->instance()->categoryResults;
        $this->assertTrue($byNumber->contains('id', $category->id));

        $byLedgerName = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'Versicherungen')->instance()->categoryResults;
        $this->assertTrue($byLedgerName->contains('id', $category->id));
    }

    /**
     * Ein eDTAS-Konto ohne Kategorie wird vorgeschlagen und legt bei Auswahl
     * eine Kategorie an.
     */
    public function test_edtas_konto_ohne_kategorie_vorschlagen_und_uebernehmen(): void
    {
        $this->bootPanel();

        $la = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '90130'], ['name' => 'Gesetzliche soziale Aufwendungen']);

        $byNumber = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '9013')->instance()->edtasResults;
        $this->assertTrue($byNumber->contains('id', $la->id));

        $byText = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'soziale')->instance()->edtasResults;
        $this->assertTrue($byText->contains('id', $la->id));

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '90130')
            ->call('setCategoryFromEdtas', $la->id);

        $category = Category::where('edtas_account', '90130')->first();
        $this->assertNotNull($category);
        $this->assertSame('Gesetzliche soziale Aufwendungen', $category->name);
        $this->assertSame($category->id, $component->get('assignCategoryId'));

        $again = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '90130')->instance()->edtasResults;
        $this->assertFalse($again->contains('id', $la->id));
    }

    /** „Als geprüft markieren" speichert nur und bleibt auf dem Umsatz. */
    public function test_als_geprueft_bleibt_auf_dem_umsatz(): void
    {
        $this->bootPanel();

        $account = BankAccount::create([
            'label' => 'Geschäftskonto',
            'business_id' => Business::first()->id,
            'currency' => 'EUR',
        ]);

        $first = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-01', 'counterparty' => 'A', 'amount' => -10.00,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-02', 'counterparty' => 'B', 'amount' => -20.00,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $first->id)
            ->call('markReviewed')
            ->assertSet('selectedTransactionId', $first->id);

        $this->assertTrue($first->refresh()->reviewed);
    }

    /** Der Zähler „Kontosatz X von Y" zählt nur ungeprüfte Umsätze. */
    public function test_zaehler_zeigt_nur_ungeprüfte_umsaetze(): void
    {
        $this->bootPanel();

        $account = BankAccount::create([
            'label' => 'Geschäftskonto', 'business_id' => Business::first()->id, 'currency' => 'EUR',
        ]);
        $make = fn (string $day, bool $reviewed) => BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => $day, 'counterparty' => 'X', 'amount' => -10.00,
            'reviewed' => $reviewed, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        $a = $make('2026-06-01', false);
        $make('2026-06-02', false);
        $make('2026-06-03', true); // bereits geprüft -> zählt nicht

        $comp = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $a->id);
        $this->assertSame(2, $comp->instance()->total);
        $this->assertSame(1, $comp->instance()->position);

        // Nach dem Prüfen des ersten: nur noch 1 offen, aktueller zählt nicht mehr mit.
        $comp->call('markReviewed');
        $this->assertSame(1, $comp->instance()->total);
        $this->assertSame(0, $comp->instance()->position);
    }

    /** Die operative Sachkonto-Suche liefert die eDTAS-Konten (edtas/gastro/kfz). */
    public function test_sachkonto_suche_edtas(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4800'], ['name' => 'Planmäßige Abschreibung auf Anlagevermögen']);

        $results = Livewire::test(Kontoumsatzdetails::class)
            ->set('ledgerSearch', '4800')
            ->instance()->ledgerResults;

        $this->assertNotEmpty($results->where('chart', 'edtas')->all());
    }
}
