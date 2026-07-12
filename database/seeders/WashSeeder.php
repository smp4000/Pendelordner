<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\WashArticle;
use App\Models\WashFreePlate;
use App\Models\WashPaymentState;
use Illuminate\Database\Seeder;

/**
 * Stammdaten für die Waschumsätze:
 *  - Kassen-Artikel je Station (Fulda mit echten EANs aus der Artikelliste,
 *    Petersberg gespiegelt mit gleichen Preisen, EANs leer = nachpflegen),
 *  - Freiwäsche-Kennzeichen (Eigenfahrzeuge),
 *  - Bedeutung der State-Codes.
 * Alles ist in der App pflegbar; hier nur sinnvolle Startwerte.
 */
class WashSeeder extends Seeder
{
    public function run(): void
    {
        // Programm-Token => [Kassen-Bezeichnung, Typ, VK, EAN Fulda, Artikelnr]
        $articles = [
            'Basis' => ['Basispflege', 'einzel', 9.95, null, null],
            'Schnell' => ['Schnellpflege', 'einzel', 8.95, '4003116400722', '623839'],
            'Rundum' => ['Rundumpflege', 'einzel', 13.95, '4003116400692', '623836'],
            'Glanz' => ['Glanzschutzpflege', 'einzel', 14.95, '4003116400708', '623837'],
            'Hochglanz' => ['Hochglanzwaesche', 'einzel', 16.95, '4003116482070', '716669'],
            'Cabrio' => ['Cabriowäsche', 'einzel', 14.95, '4003116437926', '654929'],
            'Abo' => ['Basispflege Abo', 'flatrate', 18.90, '2090039600003', '900396'],
        ];

        $fulda = Business::where('city', 'like', '%Fulda%')->orderBy('id')->first();
        $petersberg = Business::where('city', 'like', '%Petersberg%')->orderBy('id')->first();

        $sort = 0;
        foreach ($articles as $program => [$name, $type, $price, $eanFulda, $artNr]) {
            $sort++;
            foreach ([[$fulda, $eanFulda], [$petersberg, null]] as [$business, $ean]) {
                if (! $business) {
                    continue;
                }
                WashArticle::updateOrCreate(
                    ['business_id' => $business->id, 'program' => $program],
                    [
                        'name' => $name,
                        'type' => $type,
                        'price' => $price,
                        'ean' => $ean,
                        'article_number' => $artNr,
                        'ledger_account' => '6621',
                        'active' => true,
                        'sort_order' => $sort,
                    ]
                );
            }
        }

        // Eigenfahrzeuge (Freiwäsche) – stationsübergreifend, pflegbar.
        foreach (['FD-CC997', 'FD-CA63', 'FD-LS997', 'FD-AW2222', 'FD-CW12', 'FD-CW21', 'FD-CW18'] as $plate) {
            WashFreePlate::updateOrCreate(
                ['normalized' => WashFreePlate::normalize($plate)],
                ['plate' => $plate, 'category' => 'eigen', 'active' => true]
            );
        }

        // State-Codes: Bedeutung unbekannt -> als Startwert "zählt als Umsatz",
        // 8/9 zum Prüfen markiert (kann jederzeit angepasst werden).
        $states = [
            7 => ['Bezahlt', true],
            8 => ['Status 8 (bitte prüfen)', true],
            9 => ['Status 9 (bitte prüfen)', true],
        ];
        foreach ($states as $code => [$label, $counts]) {
            WashPaymentState::updateOrCreate(
                ['code' => $code],
                ['label' => $label, 'counts_as_revenue' => $counts]
            );
        }
    }
}
