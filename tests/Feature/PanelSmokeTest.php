<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_seiten_rendern_fuer_angemeldeten_benutzer(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::firstOrFail();

        $seiten = [
            '/admin',                 // Dashboard
            '/admin/bankumsatzs',     // Modul 2 (Custom-Tabelle, Filter, Ampel)
            '/admin/belegs',          // Modul 3
            '/admin/betriebs',        // Modul 7
            '/admin/kategories',      // Modul 8
            '/admin/kostenstelles',   // Modul 9
            '/admin/lieferants',      // Modul 4
            '/admin/bankkontos',      // Modul 1
        ];

        foreach ($seiten as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertSuccessful();
        }
    }
}
