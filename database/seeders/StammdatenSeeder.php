<?php

namespace Database\Seeders;

use App\Models\Betrieb;
use App\Models\Kategorie;
use App\Models\Kostenstelle;
use Illuminate\Database\Seeder;

/**
 * Grundstammdaten: Betriebe (Modul 7), Kostenstellen (Modul 9) und die
 * Standardkategorien (Modul 8) inkl. Default-Kontierung SKR03/SKR04 als
 * Vorbereitung für Modul 13.
 */
class StammdatenSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Betriebe ------------------------------------------------------
        $betriebe = [
            ['name' => 'Aral Tankstelle Petersberg', 'kurzname' => 'Aral Petersberg', 'typ' => 'tankstelle', 'ort' => 'Petersberg', 'farbe' => '#1e6cff', 'sortierung' => 1],
            ['name' => 'Tankstelle 2', 'kurzname' => 'Tankstelle 2', 'typ' => 'tankstelle', 'farbe' => '#00a3ff', 'sortierung' => 2],
            ['name' => 'Kfz-Werkstatt', 'kurzname' => 'Werkstatt', 'typ' => 'werkstatt', 'farbe' => '#f59e0b', 'sortierung' => 3],
            ['name' => 'Sachverständigenbüro', 'kurzname' => 'SV-Büro', 'typ' => 'sachverstaendigenbuero', 'farbe' => '#10b981', 'sortierung' => 4],
        ];
        foreach ($betriebe as $b) {
            Betrieb::firstOrCreate(['name' => $b['name']], $b);
        }

        $aral = Betrieb::where('kurzname', 'Aral Petersberg')->first();
        $werkstatt = Betrieb::where('typ', 'werkstatt')->first();
        $svBuero = Betrieb::where('typ', 'sachverstaendigenbuero')->first();

        // ---- Kostenstellen -------------------------------------------------
        $kostenstellen = [
            ['nummer' => '100', 'name' => 'Tankstelle', 'betrieb_id' => $aral?->id, 'farbe' => '#1e6cff', 'sortierung' => 1],
            ['nummer' => '110', 'name' => 'Shop', 'betrieb_id' => $aral?->id, 'farbe' => '#8b5cf6', 'sortierung' => 2],
            ['nummer' => '120', 'name' => 'Lotto', 'betrieb_id' => $aral?->id, 'farbe' => '#ec4899', 'sortierung' => 3],
            ['nummer' => '130', 'name' => 'Waschanlage', 'betrieb_id' => $aral?->id, 'farbe' => '#06b6d4', 'sortierung' => 4],
            ['nummer' => '200', 'name' => 'Werkstatt', 'betrieb_id' => $werkstatt?->id, 'farbe' => '#f59e0b', 'sortierung' => 5],
            ['nummer' => '300', 'name' => 'Sachverständigenbüro', 'betrieb_id' => $svBuero?->id, 'farbe' => '#10b981', 'sortierung' => 6],
        ];
        foreach ($kostenstellen as $k) {
            Kostenstelle::firstOrCreate(['name' => $k['name']], $k);
        }

        // ---- Kategorien mit Default-Kontierung -----------------------------
        // Spalten: skr03, skr04, steuerschluessel (DATEV-BU: 9=19% VSt, 8=7% VSt), satz
        $kategorien = [
            ['name' => 'Blumen', 'farbe' => '#ec4899', 'skr03_konto' => '3300', 'skr04_konto' => '5300', 'steuerschluessel' => '8', 'standard_steuersatz' => 7],
            ['name' => 'Backwaren', 'farbe' => '#d97706', 'skr03_konto' => '3300', 'skr04_konto' => '5300', 'steuerschluessel' => '8', 'standard_steuersatz' => 7],
            ['name' => 'Shop', 'farbe' => '#8b5cf6', 'skr03_konto' => '3400', 'skr04_konto' => '5400', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Getränke', 'farbe' => '#0ea5e9', 'skr03_konto' => '3400', 'skr04_konto' => '5400', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Lotto', 'farbe' => '#f43f5e', 'skr03_konto' => null, 'skr04_konto' => null, 'steuerschluessel' => null, 'standard_steuersatz' => null],
            ['name' => 'Kraftstoffe', 'farbe' => '#1e6cff', 'skr03_konto' => '3400', 'skr04_konto' => '5400', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Waschanlage', 'farbe' => '#06b6d4', 'skr03_konto' => '4240', 'skr04_konto' => '6325', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Reparaturen', 'farbe' => '#ef4444', 'skr03_konto' => '4805', 'skr04_konto' => '6460', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Werkzeug', 'farbe' => '#737373', 'skr03_konto' => '4985', 'skr04_konto' => '6845', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Telefon', 'farbe' => '#22c55e', 'skr03_konto' => '4920', 'skr04_konto' => '6805', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Internet', 'farbe' => '#16a34a', 'skr03_konto' => '4920', 'skr04_konto' => '6805', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Strom', 'farbe' => '#eab308', 'skr03_konto' => '4240', 'skr04_konto' => '6325', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Wasser', 'farbe' => '#38bdf8', 'skr03_konto' => '4240', 'skr04_konto' => '6325', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Versicherung', 'farbe' => '#64748b', 'skr03_konto' => '4360', 'skr04_konto' => '6400', 'steuerschluessel' => null, 'standard_steuersatz' => null],
            ['name' => 'Miete', 'farbe' => '#a855f7', 'skr03_konto' => '4210', 'skr04_konto' => '6310', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Marketing', 'farbe' => '#f97316', 'skr03_konto' => '4600', 'skr04_konto' => '6600', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Fahrzeuge', 'farbe' => '#0891b2', 'skr03_konto' => '4500', 'skr04_konto' => '6500', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Bürobedarf', 'farbe' => '#94a3b8', 'skr03_konto' => '4930', 'skr04_konto' => '6815', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
            ['name' => 'Sachverständigenkosten', 'farbe' => '#10b981', 'skr03_konto' => '3100', 'skr04_konto' => '5900', 'steuerschluessel' => '9', 'standard_steuersatz' => 19],
        ];
        foreach ($kategorien as $i => $k) {
            Kategorie::firstOrCreate(['name' => $k['name']], array_merge($k, ['sortierung' => $i + 1]));
        }
    }
}
