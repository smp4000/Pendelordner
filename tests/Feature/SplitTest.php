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
        $la1 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3790'], ['name' => 'Einkauf Telefonkarten']);
        $la2 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3793'], ['name' => 'e-Loading']);

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

    /** Änderungen an einer Split-Zeile speichern automatisch, ohne den Button zu klicken. */
    public function test_split_wird_automatisch_gespeichert(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Testkonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $la = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3070'], ['name' => 'Einkauf Lebensmittel, USt voll']);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-03', 'counterparty' => 'SB Union',
            'amount' => -100.00, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('toggleSplit')
            ->call('setSplitMode', 'brutto');

        // Konto auswählen -> autospeichert sofort (kein saveSplits()-Call nötig).
        $component->call('setSplitLedger', 0, $la->id);
        $this->assertSame(1, $tx->accountAssignments()->count());

        // Betrag ändern (Livewire-Feld-Update löst den updated()-Hook aus) -> wieder autospeichern.
        $component->set('splits.0.amount', '100,00');
        $this->assertEqualsWithDelta(100.00, (float) $tx->accountAssignments()->first()->amount, 0.001);

        // Position entfernen -> Aufteilung wird automatisch geleert.
        $component->call('removeSplit', 0);
        $this->assertSame(0, $tx->accountAssignments()->count());
    }

    /**
     * Ein (negativer) Betrag darf beim automatischen Speichern NICHT aus der
     * Datenbank in das Eingabefeld zurückgeschrieben werden – sonst wird die
     * laufende Eingabe überschrieben und z. B. auf 0 zurückgesetzt.
     */
    public function test_negativer_betrag_bleibt_beim_autospeichern_im_feld_erhalten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Testkonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $la = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '8093'], ['name' => 'Provision Toto, Lotto']);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-03', 'counterparty' => 'Aral', 'amount' => -100.00,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $component = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('toggleSplit')
            ->call('setSplitMode', 'brutto')
            ->call('setSplitLedger', 0, $la->id);

        // Negativen Betrag eintippen (löst updated()-Hook + Autospeichern aus).
        $component->set('splits.0.amount', '-329,36');

        // Feld behält exakt die Eingabe (nicht durch DB-Reload überschrieben/auf 0 gesetzt).
        $this->assertSame('-329,36', $component->get('splits.0.amount'));

        // Und in der Datenbank steht der negative Betrag korrekt.
        $this->assertEqualsWithDelta(-329.36, (float) $tx->accountAssignments()->first()->amount, 0.001);
    }

    public function test_splits_erscheinen_im_bericht(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Lotto Horas', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $l2610 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '2610'], ['name' => 'Agenturabrechnung Toto, Lotto']);
        $l8093 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '8093'], ['name' => 'Provision Toto, Lotto']);
        $l4700 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4700'], ['name' => 'Kosten Toto, Lotto']);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-24', 'counterparty' => 'Lotto Hessen',
            'amount' => -2581.79, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Dreizeilen-Split inkl. negativer Provisions-Zeile über die Seite speichern.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splitMode', 'brutto')
            ->set('splits', [
                ['ledger_account_id' => $l2610->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '0', 'amount' => '2.905,20'],
                ['ledger_account_id' => $l8093->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '19', 'amount' => '-329,36'],
                ['ledger_account_id' => $l4700->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '0', 'amount' => '5,95'],
            ])
            ->call('saveSplits');

        $tx->refresh();
        $this->assertSame(3, $tx->accountAssignments()->count());
        $this->assertEqualsWithDelta(-329.36, (float) $tx->accountAssignments()->where('ledger_account_id', $l8093->id)->value('amount'), 0.001);

        // Bericht-Vorspann rendern: die Split-Zeilen (Kontonummern) müssen erscheinen.
        $tx = BankTransaction::with(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount', 'accountAssignments.ledgerAccount'])->find($tx->id);
        $html = view('pdf.steuerberater', [
            'business' => Business::first(),
            'account' => $account,
            'periodLabel' => 'Juni 2026',
            'generatedAt' => '01.07.2026',
            'transactions' => collect([$tx]),
            'stats' => ['count' => 1, 'income' => 0, 'expense' => -2581.79, 'receipts' => 0, 'withoutReceipt' => 1, 'unreviewed' => 1, 'appendedFiles' => 0, 'steuerFiles' => 0],
            'receiptNumbers' => [],
            'steuerNumbers' => [],
            'steuerDocs' => collect(),
            'reportNotes' => collect(),
            'money' => fn ($v) => number_format((float) $v, 2, ',', '.') . ' €',
        ])->render();

        $this->assertStringContainsString('2610', $html);
        $this->assertStringContainsString('8093', $html);
        $this->assertStringContainsString('4700', $html);
        // Bei Aufteilung wird statt der Haupt-Konto-Spalte der Verweis gezeigt.
        $this->assertStringContainsString('Aufteilung', $html);
    }

    public function test_bericht_blendet_kategorie_und_konto_bei_split_aus(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'GS', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $la = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3070'], ['name' => 'Einkauf Lebensmittel, USt voll']);
        $category = Category::firstOrCreate(['name' => 'EinkaufTestKat'], ['active' => true]);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'category_id' => $category->id, 'ledger_account_id' => $la->id,
            'booking_date' => '2026-06-05', 'counterparty' => 'SB Union',
            'amount' => -100.00, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        $tx->accountAssignments()->create([
            'chart_of_accounts' => 'edtas', 'ledger_account_id' => $la->id,
            'tax_rate' => 19, 'amount' => 100.00, 'booking_date' => $tx->booking_date,
        ]);

        $render = fn ($t) => view('pdf.steuerberater', [
            'business' => Business::first(), 'account' => $account,
            'periodLabel' => 'Juni 2026', 'generatedAt' => '01.07.2026',
            'transactions' => collect([$t]),
            'stats' => ['count' => 1, 'income' => 0, 'expense' => -100, 'receipts' => 0, 'withoutReceipt' => 1, 'unreviewed' => 1, 'appendedFiles' => 0, 'steuerFiles' => 0],
            'receiptNumbers' => [], 'steuerNumbers' => [], 'steuerDocs' => collect(), 'reportNotes' => collect(),
            'money' => fn ($v) => number_format((float) $v, 2, ',', '.') . ' €',
        ])->render();

        // MIT Split: Kategorie-Name der Hauptzuordnung erscheint nicht.
        $tx = BankTransaction::with(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount', 'accountAssignments.ledgerAccount'])->find($tx->id);
        $this->assertStringNotContainsString('EinkaufTestKat', $render($tx));

        // OHNE Split: Kategorie wird normal angezeigt.
        $tx->accountAssignments()->delete();
        $tx = $tx->fresh(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount', 'accountAssignments.ledgerAccount']);
        $this->assertStringContainsString('EinkaufTestKat', $render($tx));
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

        // Drag & Drop: komplette neue Reihenfolge speichern.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('reorderReceipts', [$r1->id, $r2->id]);

        $this->assertSame([$r1->id, $r2->id], $tx->fresh()->receipts->pluck('id')->all());
    }
}
