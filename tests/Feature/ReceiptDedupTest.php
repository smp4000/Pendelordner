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

    public function test_inhaltliche_dublette_wird_isoliert_und_nach_bestaetigung_geloescht(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $supplier = \App\Models\Supplier::create(['name' => 'Hall Tabakwaren', 'active' => true]);

        // Original mit Rechnungsnummer.
        $original = Receipt::create([
            'type' => 'incoming_invoice', 'supplier_id' => $supplier->id,
            'invoice_number' => '160101918', 'gross_amount' => 9463.20,
        ]);

        // Gleiche Rechnung als ANDERE Datei hochladen (anderer Hash).
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<p>RECHNUNG</p><p>USt-IdNr.: DE999888777</p><p>Rechnungsnr.: 160101918</p><p>Gesamtbetrag EUR 9.463,20</p>'
        )->output();
        $supplier->update(['vat_id' => 'DE999888777']);

        $comp = Livewire::test(BelegeZuordnen::class)
            ->set('uploadFiles', [UploadedFile::fake()->createWithContent('rg-nochmal.pdf', $pdf)])
            ->call('uploadReceipts');

        // Neuer Beleg existiert, ist aber als Dublette isoliert (Verweis aufs Original).
        $dup = Receipt::whereNotNull('duplicate_of_id')->first();
        $this->assertNotNull($dup);
        $this->assertSame($original->id, $dup->duplicate_of_id);

        // Isoliert = taucht nicht in der offenen Beleg-Liste auf.
        $this->assertFalse($comp->instance()->unassignedReceipts->contains('id', $dup->id));

        // Nach Bestätigung löschen -> Datensatz + Datei weg, Original bleibt.
        $path = $dup->file_path;
        Livewire::test(BelegeZuordnen::class)->call('deleteDuplicate', $dup->id);
        $this->assertNull(Receipt::withTrashed()->find($dup->id));
        Storage::disk('belege')->assertMissing($path);
        $this->assertNotNull(Receipt::find($original->id));
    }

    public function test_dublette_ohne_rechnungsnummer_wird_ueber_lieferant_und_betrag_erkannt(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $supplier = \App\Models\Supplier::create(['name' => 'SB Union Großmarkt', 'vat_id' => 'DE811180852', 'active' => true]);

        // Original MIT Rechnungsnummer.
        $original = Receipt::create([
            'type' => 'incoming_invoice', 'supplier_id' => $supplier->id,
            'invoice_number' => '227868487', 'gross_amount' => 305.65,
        ]);

        // Gleiche Rechnung nochmal, aber OHNE erkennbare Rechnungsnummer im Text
        // (nur Lieferant per USt-Id + gleicher Betrag).
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML(
            '<p>Lieferschein-Kopie</p><p>UST-ID-Nr. DE811180852</p><p>Zahlbetrag 305,65 €</p>'
        )->output();

        Livewire::test(BelegeZuordnen::class)
            ->set('uploadFiles', [UploadedFile::fake()->createWithContent('kopie.pdf', $pdf)])
            ->call('uploadReceipts');

        $dup = Receipt::whereNotNull('duplicate_of_id')->first();
        $this->assertNotNull($dup, 'Beleg ohne Rechnungsnummer mit gleichem Lieferant+Betrag muss isoliert werden.');
        $this->assertSame($original->id, $dup->duplicate_of_id);
    }

    public function test_faelschlich_isolierter_beleg_kann_freigegeben_werden(): void
    {
        Storage::fake('belege');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $original = Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'R-1', 'gross_amount' => 10]);
        $suspect = Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'R-1', 'gross_amount' => 10, 'duplicate_of_id' => $original->id]);

        Livewire::test(BelegeZuordnen::class)->call('keepDuplicate', $suspect->id);

        $this->assertNull($suspect->fresh()->duplicate_of_id);
    }
}
