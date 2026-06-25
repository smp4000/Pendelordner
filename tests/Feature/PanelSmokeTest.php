<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_listen_rendern(): void
    {
        $user = $this->preparedUser();

        $pages = [
            '/admin',                    // Dashboard
            '/admin/bank-transactions',  // Modul 2
            '/admin/receipts',           // Modul 3
            '/admin/businesses',         // Modul 7
            '/admin/categories',         // Modul 8
            '/admin/cost-centers',       // Modul 9
            '/admin/suppliers',          // Modul 4
            '/admin/bank-accounts',      // Modul 1
            '/admin/matching-rules',     // Modul 4
            '/admin/fints-connections',  // Modul 1
            '/admin/steuerberater-bericht', // Modul 12 (Berichts-Seite)
            '/admin/kontoumsatzdetails',    // Modul 6 (3-Spalten-Ansicht)
            '/admin/account-assignments',   // Modul 13 (Kontierungen)
            '/admin/datev-export-seite',    // Modul 14 (DATEV-Export)
            '/admin/auswertungen',          // Modul 10 (Auswertungen)
            '/admin/fints-konten',          // Modul 1 (FinTS-Konten interaktiv)
            '/admin/umsaetze-importieren',  // Modul 1 (Datei-Upload-Import)
        ];

        foreach ($pages as $url) {
            $this->actingAs($user)->get($url)->assertSuccessful();
        }
    }

    public function test_formulare_rendern(): void
    {
        $user = $this->preparedUser();

        // Create-Seiten rendern die Formulare (ColorPicker, Passwortfeld, Sektionen).
        $forms = [
            '/admin/businesses/create',
            '/admin/bank-accounts/create',
            '/admin/categories/create',
            '/admin/cost-centers/create',
            '/admin/suppliers/create',
            '/admin/matching-rules/create',
            '/admin/fints-connections/create',
            '/admin/receipts/create',
            '/admin/bank-transactions/create',
        ];

        foreach ($forms as $url) {
            $this->actingAs($user)->get($url)->assertSuccessful();
        }
    }

    public function test_belegvorschau_route_liefert_die_datei(): void
    {
        Storage::fake('belege');
        $user = $this->preparedUser();

        Storage::disk('belege')->put('2026/06/test.pdf', "%PDF-1.4\nTest");
        $receipt = Receipt::create([
            'type' => 'incoming_invoice',
            'file_path' => '2026/06/test.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)
            ->get(route('beleg.datei', $receipt))
            ->assertOk();
    }

    private function preparedUser(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::firstOrFail();
    }
}
