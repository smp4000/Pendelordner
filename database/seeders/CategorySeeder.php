<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategorien mit Default-Kontierung (eDTAS-Konto, DATEV-BU-Schlüssel und
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
            ['name' => 'Blumen', 'color' => '#ec4899', 'edtas_account' => '3300', 'tax_key' => '8', 'default_tax_rate' => 7],
            ['name' => 'Backwaren', 'color' => '#d97706', 'edtas_account' => '3300', 'tax_key' => '8', 'default_tax_rate' => 7],
            ['name' => 'Shop', 'color' => '#8b5cf6', 'edtas_account' => '3400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Getränke', 'color' => '#0ea5e9', 'edtas_account' => '3400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Tabakwaren', 'color' => '#a16207', 'edtas_account' => '3400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Lotto', 'color' => '#f43f5e', 'edtas_account' => null, 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Lotto neutral', 'color' => '#fb7185', 'edtas_account' => null, 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Provision', 'color' => '#22c55e', 'edtas_account' => '8400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Kraftstoffe', 'color' => '#1e6cff', 'edtas_account' => '3400', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Waschanlage', 'color' => '#06b6d4', 'edtas_account' => '4240', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Reparaturen', 'color' => '#ef4444', 'edtas_account' => '4805', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Werkzeug', 'color' => '#737373', 'edtas_account' => '4985', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Telefon', 'color' => '#22c55e', 'edtas_account' => '4920', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Internet', 'color' => '#16a34a', 'edtas_account' => '4920', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Strom', 'color' => '#eab308', 'edtas_account' => '4240', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Wasser', 'color' => '#38bdf8', 'edtas_account' => '4240', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Versicherung', 'color' => '#64748b', 'edtas_account' => '4360', 'tax_key' => null, 'default_tax_rate' => null],
            ['name' => 'Miete', 'color' => '#a855f7', 'edtas_account' => '4210', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Marketing', 'color' => '#f97316', 'edtas_account' => '4600', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Fahrzeuge', 'color' => '#0891b2', 'edtas_account' => '4500', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Bürobedarf', 'color' => '#94a3b8', 'edtas_account' => '4930', 'tax_key' => '9', 'default_tax_rate' => 19],
            ['name' => 'Sachverständigenkosten', 'color' => '#10b981', 'edtas_account' => '3100', 'tax_key' => '9', 'default_tax_rate' => 19],
        ];

        foreach ($categories as $i => $c) {
            Category::firstOrCreate(['name' => $c['name']], array_merge($c, ['sort_order' => $i + 1, 'active' => true]));
        }
    }
}
