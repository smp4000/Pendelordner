<?php

namespace Tests\Feature;

use App\Models\Receipt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BelegeOrdnerImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordner_import_legt_belege_an_und_ueberspringt_dubletten(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('belege');

        // Import-Ordner mit drei Dateien vorbereiten (zwei davon inhaltsgleich).
        $ordner = sys_get_temp_dir() . '/import-test-' . bin2hex(random_bytes(4));
        mkdir($ordner);
        file_put_contents($ordner . '/rechnung1.pdf', 'PDF-INHALT-A');
        file_put_contents($ordner . '/rechnung2.pdf', 'PDF-INHALT-B');
        file_put_contents($ordner . '/rechnung2_kopie.pdf', 'PDF-INHALT-B'); // Dublette zu 2
        file_put_contents($ordner . '/logo.gif', 'x');                       // falsche Endung -> ignoriert

        $this->artisan('belege:ordner-import', [
            'pfad' => $ordner,
            '--keine-ocr' => true,
            '--pause' => 0,
        ])->assertSuccessful();

        // Zwei eindeutige PDFs -> zwei Belege; die inhaltsgleiche Kopie wird übersprungen.
        $this->assertSame(2, Receipt::count());
        $this->assertSame(2, Receipt::whereNotNull('file_path')->count());

        // Erneuter Lauf legt nichts Neues an (alles Dubletten).
        $this->artisan('belege:ordner-import', [
            'pfad' => $ordner,
            '--keine-ocr' => true,
            '--pause' => 0,
        ])->assertSuccessful();
        $this->assertSame(2, Receipt::count());

        // Aufräumen.
        array_map('unlink', glob($ordner . '/*'));
        rmdir($ordner);
    }

    public function test_verschieben_raeumt_ordner_auf(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('belege');

        $ordner = sys_get_temp_dir() . '/import-move-' . bin2hex(random_bytes(4));
        mkdir($ordner);
        file_put_contents($ordner . '/a.pdf', 'INHALT-A');

        $this->artisan('belege:ordner-import', [
            'pfad' => $ordner,
            '--keine-ocr' => true,
            '--pause' => 0,
            '--verschieben' => '_erledigt',
        ])->assertSuccessful();

        $this->assertSame(1, Receipt::count());
        // Datei wurde in den Unterordner verschoben.
        $this->assertFileDoesNotExist($ordner . '/a.pdf');
        $this->assertFileExists($ordner . '/_erledigt/a.pdf');

        // Aufräumen.
        @unlink($ordner . '/_erledigt/a.pdf');
        @rmdir($ordner . '/_erledigt');
        @rmdir($ordner);
    }
}
