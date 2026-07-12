<?php

namespace Tests\Feature;

use App\Filament\Pages\Waschumsaetze;
use App\Models\Business;
use App\Models\WashPayment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class WaschumsaetzePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_kassenliste_und_manuelle_zuordnung(): void
    {
        $this->seed(DatabaseSeeder::class); // Betriebe + Wasch-Artikel (WashSeeder)
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $csv = "sep=;\n"
            . "Id;Created;Payment date;Customer name;Currency;Subtotal;Total;Tax;Discount;Application fee;Surcharge;State;Description\n"
            . "10;2026-06-22T18:50:41+00:00;2026-06-22;Wetzel;eur;16.95;16.95;2.71;;0.00;;7;Christian Welle Fulda: Hochglanz (FD TL 18481)\n"
            . "11;2026-06-25T18:36:51+00:00;2026-06-25;Seidensal;eur;16.95;8.48;1.35;-8.47;0.00;;7;Christian Welle Fulda: Hochglanz (FDSJ 111)\n"
            . "12;2026-06-14T06:55:01+00:00;2026-06-14;Welle;eur;29.90;29.90;4.77;;0.00;;7;Subscription payment\n";

        $comp = Livewire::test(Waschumsaetze::class)
            ->set('filterYear', 2026)->set('filterMonth', 6)
            ->set('uploadMethod', 'card')
            ->set('uploadFile', UploadedFile::fake()->createWithContent('export.csv', $csv))
            ->call('importUpload');

        $this->assertSame(3, WashPayment::count());

        $fulda = Business::where('city', 'Fulda')->firstOrFail();

        // Kassen-Liste Fulda: 2× Hochglanz zum vollen VK, Korrektur = -8,47.
        $block = collect($comp->instance()->kassenListe)
            ->firstWhere(fn ($b) => $b['business']->id === $fulda->id);
        $this->assertNotNull($block);
        $line = collect($block['lines'])->firstWhere('program', 'Hochglanz');
        $this->assertSame(2, $line['qty']);
        $this->assertEqualsWithDelta(33.90, $line['zwischensumme'], 0.001);
        $this->assertEqualsWithDelta(25.43, $block['sum_ist'], 0.001);
        $this->assertEqualsWithDelta(-8.47, $block['correction'], 0.001);

        // Abo (12) ist ohne Station -> manuell Fulda zuordnen.
        $abo = WashPayment::where('external_id', '12')->firstOrFail();
        $this->assertNull($abo->business_id);
        $comp->call('assignStation', $abo->id, $fulda->id);
        $this->assertSame($fulda->id, $abo->fresh()->business_id);
    }
}
