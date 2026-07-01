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
            ->set('docUploads', [UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf')])
            ->call('uploadDocuments');

        $doc = SteuerDocument::first();
        $this->assertNotNull($doc);
        $this->assertSame($account->id, $doc->bank_account_id);
        $this->assertSame('Monatsrechnung', $doc->category);
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
}
