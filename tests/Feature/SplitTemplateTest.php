<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\LedgerAccount;
use App\Models\SplitTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SplitTemplateSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplitTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_vorlage_anwenden_belegt_konten_und_ust_vor(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(SplitTemplateSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Benötigte Konten sicherstellen.
        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '1616'], ['name' => 'Verrechnungskonto Avise']);
        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4500'], ['name' => 'Werbekosten']);

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-02', 'counterparty' => 'ARAL', 'amount' => -5205.37,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $tpl = SplitTemplate::where('name', 'Aral/OIL-Avis')->firstOrFail();

        $comp = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('applyTemplate', $tpl->id);

        $splits = $comp->get('splits');
        $this->assertCount(5, $splits);
        // Erste Zeile: Konto 1616, USt 0, Betrag leer.
        $this->assertNotNull($splits[0]['ledger_account_id']);
        $this->assertSame('0', $splits[0]['tax_rate']);
        $this->assertSame('', $splits[0]['amount']);
        // POLA-Zeile trägt 19 %.
        $this->assertSame('19', $splits[4]['tax_rate']);
    }

    public function test_vorlage_laedt_automatisch_beim_aufteilen_nach_empfaenger(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(SplitTemplateSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '1616'], ['name' => 'Verrechnungskonto Avise']);
        LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '4500'], ['name' => 'Werbekosten']);

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        // Umsatz von ARAL -> Vorlage "Aral/OIL-Avis" muss automatisch laden.
        $aral = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-12', 'counterparty' => 'ARAL Aktiengesellschaft',
            'amount' => -2309.86, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $comp = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $aral->id)
            ->call('toggleSplit');

        $this->assertCount(5, $comp->get('splits'));
        $this->assertSame('0', $comp->get('splits')[0]['tax_rate']);

        // Anderer Empfänger -> keine Vorlage, nur eine leere Zeile.
        $other = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-12', 'counterparty' => 'Stadtwerke Fulda',
            'amount' => -50.00, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $comp2 = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $other->id)
            ->call('toggleSplit');

        $this->assertCount(1, $comp2->get('splits'));
        $this->assertNull($comp2->get('splits')[0]['ledger_account_id']);
    }

    public function test_aktuelle_aufteilung_als_vorlage_speichern(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $la1 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '1616'], ['name' => 'Verrechnungskonto Avise']);
        $la2 = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '2645'], ['name' => 'Pfand Agentur']);

        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-02', 'counterparty' => 'X', 'amount' => -100,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splits', [
                ['ledger_account_id' => $la1->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '0', 'amount' => '50,00'],
                ['ledger_account_id' => $la2->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '19', 'amount' => '50,00'],
            ])
            ->set('newTemplateName', 'Meine Vorlage')
            ->call('saveSplitAsTemplate');

        $tpl = SplitTemplate::where('name', 'Meine Vorlage')->firstOrFail();
        $this->assertCount(2, $tpl->rows);
        $this->assertSame('1616', $tpl->rows[0]['ledger_number']);
        $this->assertSame('19', $tpl->rows[1]['tax_rate']);
    }

    public function test_vorlage_mit_ausloese_stichwort_speichern_und_automatisch_laden(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $wareneinkauf = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3400'], ['name' => 'Wareneinkauf 19 %']);

        // Vorlage mit Stichwort „SB Union" speichern.
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-01', 'counterparty' => 'SB Union Großmarkt GmbH', 'amount' => -420.90,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->set('splits', [
                ['ledger_account_id' => $wareneinkauf->id, 'ledger_label' => '', 'ledger_search' => '', 'tax_rate' => '19', 'amount' => '420,90'],
            ])
            ->set('newTemplateName', 'SB Union Wareneinkauf')
            ->set('newTemplateMatch', 'SB Union')
            ->call('saveSplitAsTemplate');

        $tpl = SplitTemplate::where('name', 'SB Union Wareneinkauf')->firstOrFail();
        $this->assertSame('SB Union', $tpl->match_counterparty);

        // Auto-Load: Stichwort steht hier im VERWENDUNGSZWECK, nicht im Empfänger.
        $other = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-05', 'counterparty' => 'Sammel-Basislastschrift',
            'purpose' => 'Firmenlastschrift SB Union RE1342330', 'amount' => -111.11,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $comp = Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $other->id)
            ->call('toggleSplit');

        $splits = $comp->get('splits');
        $this->assertCount(1, $splits);
        $this->assertSame($wareneinkauf->id, $splits[0]['ledger_account_id']);
        $this->assertSame('19', $splits[0]['tax_rate']);
    }
}
