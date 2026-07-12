<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\WashFreePlate;
use App\Models\WashPayment;
use App\Services\Wash\WashPaymentImporter;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WashPaymentImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(): string
    {
        return "sep=;\n"
            . "Id;Created;Payment date;Customer name;Currency;Subtotal;Total;Tax;Discount;Application fee;Surcharge;State;Description\n"
            . "1;2026-06-22T18:50:41+00:00;2026-06-22;Wetzel, Johann;eur;16.95;16.95;2.71;;0.00;;7;Christian Welle Fulda: Hochglanz (FD TL 18481)\n"
            . "2;2026-06-20T06:26:31+00:00;2026-06-20;Welle, Christian;eur;16.95;16.95;2.71;;0.00;;9;Welle Christian Petersberg: Hochglanz (FD-CA63)\n"
            . "3;2026-06-14T06:55:01+00:00;2026-06-14;Welle, Christian;eur;108.60;0.00;0.00;-108.60;0.00;;7;Subscription payment\n"
            . "4;2026-06-01T04:56:37+00:00;2026-06-01;Welle, Christian;eur;9.95;0.00;0.00;-9.95;0.00;;7;Christian Welle Fulda: Basis (FD-CW21)\n";
    }

    public function test_import_trennt_stationen_und_erkennt_freiwaeschen(): void
    {
        $this->seed(MasterDataSeeder::class);
        WashFreePlate::create(['plate' => 'FD-CW21', 'normalized' => WashFreePlate::normalize('FD-CW21'), 'category' => 'eigen']);

        $stats = (new WashPaymentImporter())->import($this->csv(), 'card');

        $this->assertSame(4, $stats['imported']);
        $this->assertSame(1, $stats['unassigned']); // das Abo ohne Station

        $fulda = Business::where('city', 'Fulda')->first();
        $petersberg = Business::where('city', 'Petersberg')->first();

        // 1: Fulda, Einzelwäsche, Kennzeichen erkannt, nicht gratis.
        $p1 = WashPayment::where('external_id', '1')->firstOrFail();
        $this->assertSame($fulda->id, $p1->business_id);
        $this->assertSame('Hochglanz', $p1->program);
        $this->assertSame('FD TL 18481', $p1->plate);
        $this->assertFalse($p1->is_free);
        $this->assertSame('card', $p1->payment_method);

        // 2: Petersberg erkannt.
        $p2 = WashPayment::where('external_id', '2')->firstOrFail();
        $this->assertSame($petersberg->id, $p2->business_id);

        // 3: Abo -> keine Station, Subscription, gratis (0 €).
        $p3 = WashPayment::where('external_id', '3')->firstOrFail();
        $this->assertNull($p3->business_id);
        $this->assertTrue($p3->is_subscription);
        $this->assertTrue($p3->is_free);

        // 4: gratis Eigenfahrzeug -> Kategorie "eigen" über das Kennzeichen.
        $p4 = WashPayment::where('external_id', '4')->firstOrFail();
        $this->assertTrue($p4->is_free);
        $this->assertSame('FDCW21', $p4->plate_normalized);
        $this->assertSame('eigen', $p4->free_category);
    }

    public function test_erneuter_import_ueberspringt_dubletten(): void
    {
        $this->seed(MasterDataSeeder::class);

        (new WashPaymentImporter())->import($this->csv(), 'card');
        $stats = (new WashPaymentImporter())->import($this->csv(), 'card');

        $this->assertSame(0, $stats['imported']);
        $this->assertSame(4, $stats['skipped']);
        $this->assertSame(4, WashPayment::count());
    }
}
