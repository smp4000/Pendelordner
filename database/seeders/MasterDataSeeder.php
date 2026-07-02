<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\CostCenter;
use Illuminate\Database\Seeder;

/**
 * Grundstammdaten: Betriebe (Modul 7), Kostenstellen (Modul 9) und
 * Standardkategorien (Modul 8) inkl. Default-Kontierung eDTAS.
 */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Betriebe (2 Tankstellen, gleicher Inhaber Christian Welle) ----
        // Beide teilen dieselbe E-Mail – erlaubt durch das zusammengesetzte
        // Unique (email, id) auf der Tabelle.
        $businesses = [
            [
                'name' => 'Aral Tankstelle Christian Welle',
                'short_name' => 'Aral Fulda',
                'type' => 'gas_station',
                'street' => 'Schlitzer Str. 105',
                'postal_code' => '36039',
                'city' => 'Fulda',
                'phone' => '066151681',
                'fax' => '066158723',
                'email' => 'sv.welle@aral-welle.de',
                'color' => '#1e6cff',
                'sort_order' => 1,
            ],
            [
                'name' => 'Aral Tankstelle Christian Welle',
                'short_name' => 'Aral Petersberg',
                'type' => 'gas_station',
                'street' => 'Petersberger Str. 101',
                'postal_code' => '36100',
                'city' => 'Petersberg',
                'phone' => '066165535',
                'fax' => '066158723',
                'email' => 'sv.welle@aral-welle.de',
                'color' => '#00a3ff',
                'sort_order' => 2,
            ],
        ];
        foreach ($businesses as $b) {
            // Schlüssel auf Name + Straße, da beide Betriebe denselben Namen tragen.
            Business::firstOrCreate(
                ['name' => $b['name'], 'street' => $b['street']],
                $b
            );
        }

        // ---- Kostenstellen je Standort (eigener Seeder) --------------------
        $this->call(CostCenterSeeder::class);

        // ---- Kategorien mit Default-Kontierung (eigener Seeder) -------------
        $this->call(CategorySeeder::class);
    }
}
