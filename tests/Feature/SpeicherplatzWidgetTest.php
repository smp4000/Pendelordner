<?php

namespace Tests\Feature;

use App\Filament\Widgets\SpeicherplatzWidget;
use App\Models\Receipt;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpeicherplatzWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_zeigt_db_und_beleg_groesse(): void
    {
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Beleg-Größe kommt aus der DB-Spalte file_size (kein Datei-Scan mehr).
        Receipt::create([
            'type' => 'incoming_invoice',
            'file_path' => '2026/07/a.pdf',
            'file_name' => 'a.pdf',
            'file_size' => 2048,
            'status' => 'new',
        ]);

        Livewire::test(SpeicherplatzWidget::class)
            ->assertOk()
            ->assertSee('Datenbank')
            ->assertSee('Beleg-Ordner')
            ->assertSee('1 Dateien')
            ->assertSee('Gesamt');
    }
}
