<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\CostCenter;
use App\Models\MatchingRule;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Beispiel-Lieferanten und lernfähige Zuordnungsregeln (Modul 4):
 * HBW=Blumen, Pappert=Backwaren, Telekom=Telefon, Aral=Kraftstoffe,
 * VR-Pay=Kartenzahlungen.
 */
class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $cat = fn (string $name) => Category::where('name', $name)->value('id');
        $cc = fn (string $name) => CostCenter::where('name', $name)->value('id');
        $aral = Business::where('short_name', 'Aral Petersberg')->value('id');

        $entries = [
            ['name' => 'HBW Sinsheim', 'pattern' => 'HBW', 'type' => 'counterparty', 'category' => 'Blumen', 'cost_center' => 'Shop', 'business' => $aral],
            ['name' => 'Pappert Backwaren', 'pattern' => 'PAPPERT', 'type' => 'counterparty', 'category' => 'Backwaren', 'cost_center' => 'Shop', 'business' => $aral],
            ['name' => 'Telekom Deutschland GmbH', 'pattern' => 'TELEKOM', 'type' => 'counterparty', 'category' => 'Telefon', 'cost_center' => 'Tankstelle', 'business' => $aral],
            ['name' => 'Aral / BP Europa SE', 'pattern' => 'ARAL', 'type' => 'counterparty', 'category' => 'Kraftstoffe', 'cost_center' => 'Tankstelle', 'business' => $aral],
            ['name' => 'VR-Pay (Kartenzahlungen)', 'pattern' => 'VR-PAY', 'type' => 'purpose', 'category' => 'Shop', 'cost_center' => 'Tankstelle', 'business' => $aral],
        ];

        foreach ($entries as $priority => $e) {
            $categoryId = $cat($e['category']);
            $costCenterId = $cc($e['cost_center']);
            $category = Category::find($categoryId);

            $supplier = Supplier::firstOrCreate(
                ['name' => $e['name']],
                [
                    'display_name' => $e['name'],
                    'default_category_id' => $categoryId,
                    'default_cost_center_id' => $costCenterId,
                    'default_business_id' => $e['business'],
                    'skr03_account' => $category?->skr03_account,
                    'skr04_account' => $category?->skr04_account,
                    'tax_key' => $category?->tax_key,
                ]
            );

            MatchingRule::firstOrCreate(
                ['pattern' => $e['pattern'], 'pattern_type' => $e['type']],
                [
                    'supplier_id' => $supplier->id,
                    'category_id' => $categoryId,
                    'cost_center_id' => $costCenterId,
                    'business_id' => $e['business'],
                    'skr03_account' => $category?->skr03_account,
                    'skr04_account' => $category?->skr04_account,
                    'tax_key' => $category?->tax_key,
                    'priority' => 100 - $priority,
                    'active' => true,
                ]
            );
        }
    }
}
