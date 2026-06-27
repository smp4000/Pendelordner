<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategorien mit Default-Kontierung (SKR03/SKR04, DATEV-BU-Schlüssel und
 * Standard-Steuersatz). Idempotent über firstOrCreate(name), daher gefahrlos
 * mehrfach ausführbar. Aufruf einzeln: php artisan db:seed --class=CategorySeeder
 *
 * tax_key (DATEV-BU): 9 = 19% VSt, 8 = 7% VSt
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Blumen', 'color' => '#ec4899', 'skr03_account' => '3300', 'skr04_account' => '5300', 'tax_key' => '8', 'default_tax_rate' => 7],
            ['name' => 'Backwaren', 'color' => '#d97706', 'skr03_account' => '3300', 'skr04_account' => '5300', 'tax_key' => '8', 'default_tax_rate' => 7],
            ['name' => 'Shop', 'color' => '#8b5cf6', 'skr03_account' => '3400', 'skr04_account' => '5400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Getränke', 'color' => '#0ea5e9', 'skr03_account' => '3400', 'skr04_account' => '5400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Tabakwaren', 'color' => '#a16207', 'skr03_account' => '3400', 'skr04_account' => '5400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Lotto', 'color' => '#f43f5e', 'skr03_account' => null, 'skr04_account' => null, 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Lotto neutral', 'color' => '#fb7185', 'skr03_account' => null, 'skr04_account' => null, 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Provision', 'color' => '#22c55e', 'skr03_account' => '8400', 'skr04_account' => '4400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Kraftstoffe', 'color' => '#1e6cff', 'skr03_account' => '3400', 'skr04_account' => '5400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Waschanlage', 'color' => '#06b6d4', 'skr03_account' => '4240', 'skr04_account' => '6325', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Reparaturen', 'color' => '#ef4444', 'skr03_account' => '4805', 'skr04_account' => '6460', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Werkzeug', 'color' => '#737373', 'skr03_account' => '4985', 'skr04_account' => '6845', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Telefon', 'color' => '#22c55e', 'skr03_account' => '4920', 'skr04_account' => '6805', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Internet', 'color' => '#16a34a', 'skr03_account' => '4920', 'skr04_account' => '6805', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Strom', 'color' => '#eab308', 'skr03_account' => '4240', 'skr04_account' => '6325', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Wasser', 'color' => '#38bdf8', 'skr03_account' => '4240', 'skr04_account' => '6325', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Versicherung', 'color' => '#64748b', 'skr03_account' => '4360', 'skr04_account' => '6400', 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Miete', 'color' => '#a855f7', 'skr03_account' => '4210', 'skr04_account' => '6310', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Marketing', 'color' => '#f97316', 'skr03_account' => '4600', 'skr04_account' => '6600', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Fahrzeuge', 'color' => '#0891b2', 'skr03_account' => '4500', 'skr04_account' => '6500', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Bürobedarf', 'color' => '#94a3b8', 'skr03_account' => '4930', 'skr04_account' => '6815', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Sachverständigenkosten', 'color' => '#10b981', 'skr03_account' => '3100', 'skr04_account' => '5900', 'tax_key' => '9', 'default_tax_rate' => 19],
        ];

        foreach ($categories as $i => $c) {
            Category::firstOrCreate(['name' => $c['name']], array_merge($c, ['sort_order' => $i + 1, 'active' => true]));
        }
    }
}
