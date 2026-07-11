<?php

namespace Tests\Feature;

use App\Filament\Pages\BelegeZuordnen;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BelegeZuordnenTest extends TestCase
{
    use RefreshDatabase;

    public function test_seite_rendert_mit_rechter_vorschau(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Ein offener Beleg mit Datei -> hat eine preview_url und damit den
        // Vorschau-Button; die Vorschau-Schublade (belegVorschau) muss da sein.
        Receipt::create([
            'type' => 'incoming_invoice',
            'file_path' => '2026/07/test.pdf',
            'file_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'invoice_number' => 'RE-123',
            'status' => 'new',
        ]);

        Livewire::test(BelegeZuordnen::class)
            ->assertOk()
            ->assertSee('belegVorschau', false)
            ->assertSee('Vorschau ▸');
    }
}
