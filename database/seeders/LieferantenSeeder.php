<?php

namespace Database\Seeders;

use App\Models\Betrieb;
use App\Models\Kategorie;
use App\Models\Kostenstelle;
use App\Models\Lieferant;
use App\Models\ZuordnungsRegel;
use Illuminate\Database\Seeder;

/**
 * Beispiel-Lieferanten und die zugehörigen lernfähigen Zuordnungsregeln
 * (Modul 4): HBW=Blumen, Pappert=Backwaren, Telekom=Telefon, Aral=Tankstelle,
 * VR-Pay=Kartenzahlungen.
 */
class LieferantenSeeder extends Seeder
{
    public function run(): void
    {
        $kat = fn (string $name) => Kategorie::where('name', $name)->value('id');
        $ks = fn (string $name) => Kostenstelle::where('name', $name)->value('id');
        $aral = Betrieb::where('kurzname', 'Aral Petersberg')->value('id');

        // name, muster, muster_typ, kategorie, kostenstelle, betrieb
        $eintraege = [
            ['name' => 'HBW Sinsheim', 'muster' => 'HBW', 'typ' => 'empfaenger', 'kategorie' => 'Blumen', 'kostenstelle' => 'Shop', 'betrieb' => $aral],
            ['name' => 'Pappert Backwaren', 'muster' => 'PAPPERT', 'typ' => 'empfaenger', 'kategorie' => 'Backwaren', 'kostenstelle' => 'Shop', 'betrieb' => $aral],
            ['name' => 'Telekom Deutschland GmbH', 'muster' => 'TELEKOM', 'typ' => 'empfaenger', 'kategorie' => 'Telefon', 'kostenstelle' => 'Tankstelle', 'betrieb' => $aral],
            ['name' => 'Aral / BP Europa SE', 'muster' => 'ARAL', 'typ' => 'empfaenger', 'kategorie' => 'Kraftstoffe', 'kostenstelle' => 'Tankstelle', 'betrieb' => $aral],
            ['name' => 'VR-Pay (Kartenzahlungen)', 'muster' => 'VR-PAY', 'typ' => 'verwendungszweck', 'kategorie' => 'Shop', 'kostenstelle' => 'Tankstelle', 'betrieb' => $aral],
        ];

        foreach ($eintraege as $prio => $e) {
            $kategorieId = $kat($e['kategorie']);
            $kostenstelleId = $ks($e['kostenstelle']);

            $lieferant = Lieferant::firstOrCreate(
                ['name' => $e['name']],
                [
                    'anzeigename' => $e['name'],
                    'standard_kategorie_id' => $kategorieId,
                    'standard_kostenstelle_id' => $kostenstelleId,
                    'standard_betrieb_id' => $e['betrieb'],
                    'skr03_konto' => Kategorie::find($kategorieId)?->skr03_konto,
                    'skr04_konto' => Kategorie::find($kategorieId)?->skr04_konto,
                    'steuerschluessel' => Kategorie::find($kategorieId)?->steuerschluessel,
                ]
            );

            ZuordnungsRegel::firstOrCreate(
                ['muster' => $e['muster'], 'muster_typ' => $e['typ']],
                [
                    'lieferant_id' => $lieferant->id,
                    'kategorie_id' => $kategorieId,
                    'kostenstelle_id' => $kostenstelleId,
                    'betrieb_id' => $e['betrieb'],
                    'skr03_konto' => Kategorie::find($kategorieId)?->skr03_konto,
                    'skr04_konto' => Kategorie::find($kategorieId)?->skr04_konto,
                    'steuerschluessel' => Kategorie::find($kategorieId)?->steuerschluessel,
                    'prioritaet' => 100 - $prio,
                    'aktiv' => true,
                ]
            );
        }
    }
}
