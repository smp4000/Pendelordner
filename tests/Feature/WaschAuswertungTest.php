<?php

namespace Tests\Feature;

use App\Filament\Pages\WaschAuswertung;
use App\Models\User;
use App\Services\Wash\WashPaymentImporter;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaschAuswertungTest extends TestCase
{
    use RefreshDatabase;

    public function test_kennzahlen_und_controlling_pdf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $csv = "sep=;\n"
            . "Id;Created;Payment date;Customer name;Currency;Subtotal;Total;Tax;Discount;Application fee;Surcharge;State;Description\n"
            . "30;2026-06-10T10:00:00+00:00;2026-06-10;Wetzel;eur;16.95;16.95;2.71;;0.00;;7;Christian Welle Fulda: Hochglanz (FD TL 1)\n"
            . "31;2026-06-12T10:00:00+00:00;2026-06-12;Wetzel;eur;16.95;16.95;2.71;;0.00;;7;Christian Welle Fulda: Hochglanz (FD TL 2)\n"
            . "32;2026-05-04T10:00:00+00:00;2026-05-04;Meier;eur;9.95;9.95;1.59;;0.00;;7;Christian Welle Fulda: Basis (FD XY 3)\n"
            . "33;2026-07-01T10:00:00+00:00;2026-07-01;Schulz;eur;13.95;13.95;2.23;;0.00;;7;Christian Welle Fulda: Rundum (FD ZZ 4)\n";
        (new WashPaymentImporter())->import($csv, 'card');

        $comp = Livewire::test(WaschAuswertung::class)->set('filterYear', 2026)->set('filterStation', 0);

        $data = $comp->instance()->data;
        $this->assertSame(4, $data['kpi']['count']);
        $this->assertEqualsWithDelta(57.80, $data['kpi']['brutto'], 0.001);
        $this->assertEqualsWithDelta(14.45, $data['kpi']['avg'], 0.01);
        $this->assertSame(3, $data['kpi']['kunden']);
        // Meistverkauft: Hochglanz (2×) vorn.
        $this->assertSame('Hochglanz', $data['programs'][0]['program']);
        $this->assertSame(2, $data['programs'][0]['count']);
        // Umsatz je Monat.
        $this->assertEqualsWithDelta(33.90, $data['byMonth'][6], 0.001);
        $this->assertEqualsWithDelta(9.95, $data['byMonth'][5], 0.001);

        $comp->call('downloadPdf')->assertFileDownloaded();
    }
}
