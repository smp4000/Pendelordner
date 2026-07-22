<?php

namespace Tests\Feature;

use App\Filament\Widgets\SpeicherplatzWidget;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SpeicherplatzWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_zeigt_db_und_beleg_groesse(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Storage::disk('belege')->put('2026/07/a.pdf', 'hallo welt');

        Livewire::test(SpeicherplatzWidget::class)
            ->assertOk()
            ->assertSee('Datenbank')
            ->assertSee('Beleg-Ordner')
            ->assertSee('1 Dateien')
            ->assertSee('Gesamt');
    }
}
