<?php

namespace Tests\Feature;

use App\Filament\Pages\SteuerbueroHinweise;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\SteuerDocument;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SteuerDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_wird_hochgeladen_und_zugeordnet(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        Livewire::test(SteuerbueroHinweise::class)
            ->set('docAccount', $account->id)
            ->set('docYear', 2026)
            ->set('docMonth', 6)
            ->set('docCategory', 'Monatsrechnung')
            ->set('docNote', 'Bitte auf Konto 1900 buchen')
            ->set('docUploads', [UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf')])
            ->call('uploadDocuments');

        $doc = SteuerDocument::first();
        $this->assertNotNull($doc);
        $this->assertSame($account->id, $doc->bank_account_id);
        $this->assertSame('Monatsrechnung', $doc->category);
        $this->assertSame('Bitte auf Konto 1900 buchen', $doc->note);
        $this->assertSame(6, $doc->period->month);
        $this->assertSame(2026, $doc->period->year);
        Storage::disk('belege')->assertExists($doc->file_path);

        // Dieselbe Datei erneut -> Dublette wird übersprungen.
        Livewire::test(SteuerbueroHinweise::class)
            ->set('docAccount', $account->id)
            ->set('docYear', 2026)
            ->set('docMonth', 6)
            ->set('docUploads', [UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf')])
            ->call('uploadDocuments');

        $this->assertSame(1, SteuerDocument::count());
    }

    public function test_steuerdokumente_ohne_druckhaken_kommen_in_die_zip_sicherung(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);

        $account = BankAccount::create(['label' => 'Konto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        Storage::disk('belege')->put('2026/07/gedruckt.pdf', 'A');
        Storage::disk('belege')->put('2026/07/nur-speichern.pdf', 'B');

        // Eine Datei mit Druck-Haken, eine ohne.
        SteuerDocument::create(['bank_account_id' => $account->id, 'period' => '2026-07-01',
            'category' => 'Monatsrechnung', 'file_path' => '2026/07/gedruckt.pdf', 'include_in_report' => true]);
        SteuerDocument::create(['bank_account_id' => $account->id, 'period' => '2026-07-01',
            'category' => 'Hinweis Bank', 'file_path' => '2026/07/nur-speichern.pdf',
            'file_name' => 'Mitteilung Bank.pdf', 'include_in_report' => false]);

        $service = new \App\Services\Pdf\PdfReportService();
        $from = \Illuminate\Support\Carbon::parse('2026-07-01');
        $to = \Illuminate\Support\Carbon::parse('2026-07-31');

        $files = $service->unprintedSteuerDocumentFiles($from, $to, $account);

        // Nur die Datei OHNE Druck-Haken ist dabei (die gedruckte ist schon im Bericht).
        $this->assertCount(1, $files);
        $this->assertStringContainsString('nur-speichern', $files[0]['absolute']);
        // Dateiname nutzt den ORIGINALnamen (nicht den zufälligen Speichernamen).
        $this->assertStringContainsString('Mitteilung Bank', $files[0]['name']);
        $this->assertStringNotContainsString('nur-speichern', $files[0]['name']);
    }

    public function test_dokument_kann_je_zeile_konto_monat_kategorie_aendern(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $a1 = BankAccount::create(['label' => 'Konto A', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $a2 = BankAccount::create(['label' => 'Konto B', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        Storage::disk('belege')->put('2026/06/x.pdf', 'X');
        $doc = SteuerDocument::create(['bank_account_id' => $a1->id, 'period' => '2026-06-01',
            'category' => 'Monatsrechnung', 'file_path' => '2026/06/x.pdf', 'include_in_report' => true]);

        Livewire::test(SteuerbueroHinweise::class)
            ->call('setDocAccount', $doc->id, $a2->id)
            ->call('setDocMonth', $doc->id, 8)
            ->call('setDocYear', $doc->id, 2025)
            ->call('setDocCategory', $doc->id, 'Kontoauszug');

        $doc->refresh();
        $this->assertSame($a2->id, $doc->bank_account_id);
        $this->assertSame(8, $doc->period->month);
        $this->assertSame(2025, $doc->period->year);
        $this->assertSame('Kontoauszug', $doc->category);
    }

    public function test_bulk_loeschen_und_reihenfolge_sortieren(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $d1 = SteuerDocument::create(['bank_account_id' => $account->id, 'period' => '2026-06-01', 'category' => 'Kontoauszug', 'file_path' => '2026/06/1.pdf', 'sort_order' => 0]);
        $d2 = SteuerDocument::create(['bank_account_id' => $account->id, 'period' => '2026-06-01', 'category' => 'Kontoauszug', 'file_path' => '2026/06/2.pdf', 'sort_order' => 1]);
        $d3 = SteuerDocument::create(['bank_account_id' => $account->id, 'period' => '2026-06-01', 'category' => 'Kontoauszug', 'file_path' => '2026/06/3.pdf', 'sort_order' => 2]);

        // Reihenfolge per Drag & Drop: d3, d1, d2.
        Livewire::test(SteuerbueroHinweise::class)
            ->call('reorderDocuments', [$d3->id, $d1->id, $d2->id]);
        $this->assertSame(0, $d3->fresh()->sort_order);
        $this->assertSame(1, $d1->fresh()->sort_order);
        $this->assertSame(2, $d2->fresh()->sort_order);

        // Zwei auswählen und gesammelt löschen.
        Livewire::test(SteuerbueroHinweise::class)
            ->set('selectedDocs', [(string) $d1->id, (string) $d2->id])
            ->call('deleteSelectedDocs');
        $this->assertNull(SteuerDocument::find($d1->id));
        $this->assertNull(SteuerDocument::find($d2->id));
        $this->assertNotNull(SteuerDocument::find($d3->id));
    }

    public function test_kategorie_kann_angelegt_und_ausgewaehlt_werden(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SteuerbueroHinweise::class)
            ->set('newCategoryText', 'Kundenrechnung')
            ->call('addCategory')
            ->assertSet('docCategory', 'Kundenrechnung')
            ->assertSet('showNewCategory', false);

        $this->assertDatabaseHas('steuer_categories', ['name' => 'Kundenrechnung']);

        // Erscheint in den Auswahl-Optionen (neben den Vorgaben).
        $options = Livewire::test(SteuerbueroHinweise::class)->instance()->categoryOptions;
        $this->assertContains('Kundenrechnung', $options);
        $this->assertContains('Monatsrechnung', $options);
    }

    public function test_hinweis_text_wird_hinzugefuegt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SteuerbueroHinweise::class)
            ->set('newNoteText', 'Bitte auf Konto 1900 buchen')
            ->call('addNoteText')
            ->assertSet('docNote', 'Bitte auf Konto 1900 buchen')
            ->assertSet('showNewNote', false);

        $this->assertDatabaseHas('steuer_note_texts', ['text' => 'Bitte auf Konto 1900 buchen']);
    }
}
