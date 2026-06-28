<?php

namespace App\Services\Plan;

use App\Models\BusinessPlan;

/**
 * Legt für einen neuen Geschäftsplan die Standard-Positionen an – exakt nach
 * dem Aufbau der Aral/GP-OIL-Vorlage (Geschäftsplanübersicht). Für jede
 * Position wird je Planjahr ein Wert mit 0 € angelegt (Marge vorbelegt).
 */
class BusinessPlanTemplate
{
    /**
     * Umsatzzeilen: [Gruppe => [ [Bezeichnung, Standard-BVD-%], ... ]].
     * 100 % = reines Provisions-/Agenturgeschäft (Umsatz = Rohertrag).
     */
    public const REVENUE = [
        'Kraft- / Schmierstoffe' => [
            ['Provision: Vergaserkraftstoffe', 100],
            ['Provision: Dieselkraftstoffe', 100],
            ['Provision: AdBlue', 100],
            ['Provision: Autogas / LPG', 100],
            ['sonstige Agenturware', 100],
        ],
        'sonstige Provisionsgeschäfte' => [
            ['Provision - Telefonkarten', 100],
            ['Provision - sonstige', 100],
        ],
        'Shop / Bistro' => [
            ['Motorenöle / AdBlue (Eigenware)', 60],
            ['Zubehör / Ersatzteile', 42.5],
            ['Tabakwaren', 13.6],
            ['Karten, Bücher, Zeitschriften', 19],
            ['Süßwaren', 52],
            ['Eis', 45],
            ['Getränke', 52],
            ['Lebensmittel', 45],
            ['Sonstige Waren', 30],
            ['Backshop', 55],
            ['Gastronomie', 0],
            ['Kaffeeautomat', 69],
        ],
        'Wagenpflege / Werkstatt' => [
            ['Autowaschanlage', 100],
            ['Münzgeräte / Wagenpflege', 100],
            ['Kfz-Dienstleistung / Werkstatt', 0],
        ],
        'Sonstige Einnahmen' => [
            ['Sonstige Einnahmen', 100],
            ['Nebengeschäft', 21],
            ['a.o. Ertrag', 100],
        ],
    ];

    /** Kostenzeilen (ohne Marge). */
    public const COSTS = [
        'Personalkosten',
        'Personalkosten Sonstige',
        'Pacht - Station',
        'Raum- und Nebenkosten',
        'Gerätemieten / Leasing',
        'Reparaturen / Wartung',
        'Abschreibungen (exkl. Kfz)',
        'Versicherungen / Gebühren',
        'Kfz.-Kosten',
        'Hilfs- und Verbrauchsstoffe',
        'Verwaltung',
        'Werbung',
        'Beratung',
        'Zinsen- und Geldkosten',
        'Sonstige Kosten',
        'Warenverluste',
        'A.O. Aufwendungen',
    ];

    /** Erzeugt alle Standard-Positionen samt Jahres-Werten (0 €) für den Plan. */
    public function apply(BusinessPlan $plan): void
    {
        $years = $plan->years();
        $sort = 0;

        foreach (self::REVENUE as $group => $rows) {
            foreach ($rows as [$label, $margin]) {
                $line = $plan->lines()->create([
                    'section' => 'revenue',
                    'category' => $group,
                    'label' => $label,
                    'has_margin' => true,
                    'sort_order' => $sort++,
                ]);
                foreach ($years as $year) {
                    $line->values()->create(['year' => $year, 'amount' => 0, 'margin' => $margin]);
                }
            }
        }

        foreach (self::COSTS as $label) {
            $line = $plan->lines()->create([
                'section' => 'cost',
                'category' => 'Kosten',
                'label' => $label,
                'has_margin' => false,
                'sort_order' => $sort++,
            ]);
            foreach ($years as $year) {
                $line->values()->create(['year' => $year, 'amount' => 0, 'margin' => null]);
            }
        }
    }
}
