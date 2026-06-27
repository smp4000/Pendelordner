<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\CostCenter;
use Illuminate\Database\Seeder;

/**
 * Kostenstellen je Standort (Modul 9). Tankstelle/Waschanlage werden dem
 * jeweiligen Betrieb (Fulda 36039 / Petersberg 36100) zugeordnet, "Privat"
 * ist betriebsübergreifend. Idempotent über firstOrCreate(name).
 * Einzeln ausführbar: php artisan db:seed --class=CostCenterSeeder
 */
class CostCenterSeeder extends Seeder
{
    public function run(): void
    {
        $fulda = Business::where('postal_code', '36039')->first();
        $petersberg = Business::where('postal_code', '36100')->first();

        $costCenters = [
            ['name' => 'Tankstelle Petersberg', 'number' => '200', 'business_id' => $petersberg?->id, 'color' => '#00a3ff'],
            ['name' => 'Waschanlage Petersberg', 'number' => '210', 'business_id' => $petersberg?->id, 'color' => '#22d3ee'],
            ['name' => 'Tankstelle Fulda', 'number' => '100', 'business_id' => $fulda?->id, 'color' => '#1e6cff'],
            ['name' => 'Waschanlage Fulda', 'number' => '110', 'business_id' => $fulda?->id, 'color' => '#06b6d4'],
            ['name' => 'Privat', 'number' => '900', 'business_id' => null, 'color' => '#64748b'],
        ];

        foreach ($costCenters as $i => $c) {
            CostCenter::firstOrCreate(
                ['name' => $c['name']],
                array_merge($c, ['sort_order' => $i + 1, 'active' => true]),
            );
        }
    }
}
