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

        $pages = [
            '/admin',                    // Dashboard
            '/admin/bank-transactions',  // Modul 2 (Custom-Tabelle, Filter, Ampel)
            '/admin/receipts',           // Modul 3
            '/admin/businesses',         // Modul 7
            '/admin/categories',         // Modul 8
            '/admin/cost-centers',       // Modul 9
            '/admin/suppliers',          // Modul 4
            '/admin/bank-accounts',      // Modul 1
        ];

        foreach ($pages as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertSuccessful();
        }
    }
}
