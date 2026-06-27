<?php

namespace Tests\Feature;

use App\Filament\Pages\BelegeZuordnen;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptDedupTest extends TestCase
{
    use RefreshDatabase;

    public function test_gleiche_datei_wird_nicht_doppelt_angelegt(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $content = "%PDF-1.4\nRechnung Testbeleg 12345";

        Livewire::test(BelegeZuordnen::class)
            ->set('uploadFiles', [UploadedFile::fake()->createWithContent('rg.pdf', $content)])
            ->call('uploadReceipts');

        $this->assertSame(1, Receipt::count());

        // Identische Datei erneut hochladen -> wird als Dublette übersprungen.
        Livewire::test(BelegeZuordnen::class)
            ->set('uploadFiles', [UploadedFile::fake()->createWithContent('rg-kopie.pdf', $content)])
            ->call('uploadReceipts');

        $this->assertSame(1, Receipt::count());
    }
}
