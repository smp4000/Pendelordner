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
            ->set('data.bank_account_id', $account->id)
            ->set('data.year', '2026')
            ->set('data.month', '6')
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
            ->set('data.bank_account_id', $account->id)
            ->set('data.year', '2026')
            ->set('data.month', '6')
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
            'category' => 'Hinweis Bank', 'file_path' => '2026/07/nur-speichern.pdf', 'include_in_report' => false]);

        $service = new \App\Services\Pdf\PdfReportService();
        $from = \Illuminate\Support\Carbon::parse('2026-07-01');
        $to = \Illuminate\Support\Carbon::parse('2026-07-31');

        $files = $service->unprintedSteuerDocumentFiles($from, $to, $account);

        // Nur die Datei OHNE Druck-Haken ist dabei (die gedruckte ist schon im Bericht).
        $this->assertCount(1, $files);
        $this->assertStringContainsString('nur-speichern', $files[0]['absolute']);
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
