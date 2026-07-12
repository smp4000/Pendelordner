<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\WashArticle;
use App\Models\WashFreePlate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WashSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_legt_beide_stationen_an(): void
    {
        $this->seed(DatabaseSeeder::class);

        $fulda = Business::where('city', 'Fulda')->firstOrFail();
        $petersberg = Business::where('city', 'Petersberg')->firstOrFail();

        // Beide Stationen haben alle 8 Programme.
        $this->assertSame(8, WashArticle::where('business_id', $fulda->id)->count());
        $this->assertSame(8, WashArticle::where('business_id', $petersberg->id)->count());

        // Produkt-EAN (4003116…) an beiden Stationen gleich.
        $this->assertSame('4003116482070',
            WashArticle::where('business_id', $fulda->id)->where('program', 'Hochglanz')->value('ean'));
        $this->assertSame('4003116482070',
            WashArticle::where('business_id', $petersberg->id)->where('program', 'Hochglanz')->value('ean'));

        // In-House-Code (209x) nur Fulda; Petersberg leer (nachzutragen).
        $this->assertSame('2090039600003',
            WashArticle::where('business_id', $fulda->id)->where('program', 'Abo')->value('ean'));
        $this->assertNull(
            WashArticle::where('business_id', $petersberg->id)->where('program', 'Abo')->value('ean'));

        // Alle 7 Eigenfahrzeug-Kennzeichen.
        $this->assertSame(7, WashFreePlate::where('category', 'eigen')->count());
    }
}
