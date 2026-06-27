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

    /** Die Kategorie-Suche findet per Name UND per SKR03-Konto (Nummer/Bezeichnung). */
    public function test_kategorie_suche_per_name_und_skr03(): void
    {
        $this->bootPanel();

        LedgerAccount::firstOrCreate(['chart' => 'skr03', 'number' => '4360'], ['name' => 'Versicherungen']);
        $category = Category::firstOrCreate(
            ['name' => 'Versicherung'],
            ['active' => true, 'skr03_account' => '4360'],
        );

        // Treffer über den Kategorienamen
        $byName = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'Versich')
            ->instance()->categoryResults;
        $this->assertTrue($byName->contains('id', $category->id));

        // Treffer über die SKR03-Kontonummer
        $byNumber = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '4360')
            ->instance()->categoryResults;
        $this->assertTrue($byNumber->contains('id', $category->id));

        // Treffer über die SKR03-Kontobezeichnung
        $byLedgerName = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'Versicherungen')
            ->instance()->categoryResults;
        $this->assertTrue($byLedgerName->contains('id', $category->id));
    }

    /**
     * Ein SKR03-Konto ohne Kategorie (z. B. 4130) wird in der Suche per Nummer
     * UND per Text vorgeschlagen und legt bei Auswahl eine Kategorie an.
     */
    public function test_skr03_konto_ohne_kategorie_vorschlagen_und_uebernehmen(): void
    {
        $this->bootPanel();

        $la = LedgerAccount::firstOrCreate(['chart' => 'skr03', 'number' => '4130'], ['name' => 'Gesetzliche soziale Aufwendungen']);

        // Vorschlag per Nummer-Teil
        $byNumber = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '413')
            ->instance()->skrResults;
        $this->assertTrue($byNumber->contains('id', $la->id));

        // Vorschlag per Text-Teil
        $byText = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', 'soziale')
            ->instance()->skrResults;
        $this->assertTrue($byText->contains('id', $la->id));

        // Übernahme legt Kategorie mit SKR03-Zuordnung an und wählt sie
        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '4130')
            ->call('setCategoryFromSkr', $la->id);

        $category = Category::where('skr03_account', '4130')->first();
        $this->assertNotNull($category);
        $this->assertSame('Gesetzliche soziale Aufwendungen', $category->name);
        $this->assertSame($category->id, $component->get('assignCategoryId'));

        // Ist die Kategorie einmal angelegt, taucht das Konto nicht mehr als
        // SKR-Vorschlag auf (sondern als Kategorie-Treffer).
        $again = Livewire::test(Kontoumsatzdetails::class)
            ->set('categorySearch', '4130')
            ->instance()->skrResults;
        $this->assertFalse($again->contains('id', $la->id));
    }

    /** „Als geprüft markieren" speichert nur und bleibt auf dem Umsatz (kein Weiterspringen). */
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
            ->assertSet('selectedTransactionId', $first->id); // bleibt stehen

        $this->assertTrue($first->refresh()->reviewed);
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
