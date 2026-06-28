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

    /** Standard-Bemessungsgrundlagen der Shopumsatzpacht: [Bezeichnung, Quelle]. */
    public const LEASE_BASES = [
        ['Tabak', 'tabak'],
        ['Autowaschanlage', 'wasch'],
        ['Shop (ohne Tabak, KBZ, Telefonkarten)', 'shop_rest'],
        ['Lotto / Glücksspiel', 'manual'],
    ];

    /** Standard-Kapitalbedarf-Positionen: [Bezeichnung, Art der Finanzierung]. */
    public const FINANCINGS = [
        ['Bürgschaft / Sicherheiten', 'Bankbürgschaft'],
        ['Einstandszahlungen', 'Einstandszahlungen'],
        ['Warenbestand', 'Warenbestand'],
        ['Anlagevermögen', 'Anlagevermögen'],
        ['sonstige Sicherheiten', 'sonstige Sicherheiten'],
        ['sonstige Anschaffungen', 'sonstige Anschaffungen'],
    ];

    /** Standard-Lohnzeilen je Bereich: [Bezeichnung, Gruppe, ist Abzug, Bereich]. */
    public const STAFF = [
        // Shop
        ['Kassenschicht Mo.–Do.', 'Kassenschichten', false, 'shop'],
        ['Kassenschicht Fr.', 'Kassenschichten', false, 'shop'],
        ['Kassenschicht Sa.', 'Kassenschichten', false, 'shop'],
        ['Kassenschicht So.', 'Kassenschichten', false, 'shop'],
        ['Backshop', 'Zusatzstunden', false, 'shop'],
        ['Schichtwechsel', 'Zusatzstunden', false, 'shop'],
        ['Sonstige', 'Zusatzstunden', false, 'shop'],
        ['Eigenanteil Unternehmer', 'Korrekturen', true, 'shop'],
        // Werkstatt / Kfz-Aufbereitung
        ['Werkstatt Mo.–Do.', 'Schichten', false, 'werkstatt'],
        ['Werkstatt Fr.', 'Schichten', false, 'werkstatt'],
        ['Werkstatt Sa.', 'Schichten', false, 'werkstatt'],
        ['Kfz-Aufbereitung 1', 'Zusatzstunden', false, 'werkstatt'],
        ['Kfz-Aufbereitung 2', 'Zusatzstunden', false, 'werkstatt'],
        ['Eigenanteil Unternehmer', 'Korrekturen', true, 'werkstatt'],
        // Gastronomie
        ['Gastro Mo.–Do.', 'Kassenschichten', false, 'gastro'],
        ['Gastro Fr.', 'Kassenschichten', false, 'gastro'],
        ['Gastro Sa.', 'Kassenschichten', false, 'gastro'],
        ['Gastro So.', 'Kassenschichten', false, 'gastro'],
        ['Koch', 'Zusatzstunden', false, 'gastro'],
        ['Service', 'Zusatzstunden', false, 'gastro'],
        ['Schichtwechsel', 'Zusatzstunden', false, 'gastro'],
        ['Eigenanteil Unternehmer', 'Korrekturen', true, 'gastro'],
    ];

    /** Lesbare Bereichsnamen. */
    public const STAFF_AREAS = ['shop' => 'Shop', 'werkstatt' => 'Werkstatt / Kfz-Aufbereitung', 'gastro' => 'Gastronomie'];

    /** Erzeugt alle Standard-Positionen samt Jahres-Werten (0 €) für den Plan. */
    public function apply(BusinessPlan $plan): void
    {
        $years = $plan->years();
        $sort = 0;

        foreach (self::STAFF as $i => [$label, $group, $deduction, $area]) {
            $line = $plan->staffLines()->create([
                'area' => $area,
                'category' => $group,
                'label' => $label,
                'is_deduction' => $deduction,
                'sort_order' => $i,
            ]);
            foreach ($years as $year) {
                $line->values()->create([
                    'year' => $year,
                    'hours_per_day' => 0,
                    'days_per_week' => 0,
                    'hourly_wage' => 0,
                ]);
            }
        }

        // Bemessungsgrundlagen der Shopumsatzpacht + Pacht-Startjahre vorbelegen.
        foreach (self::LEASE_BASES as $i => [$label, $source]) {
            $plan->leaseBases()->create([
                'label' => $label,
                'source' => $source,
                'rate_pct' => 0,
                'sort_order' => $i,
            ]);
        }
        $plan->update([
            'umsatzpacht_start_year' => $plan->year_from,
            'festpacht_start_year' => $plan->year_from,
        ]);

        // Pacht-Stufen (1.–4.): erste Stufe ab Planbeginn, Rest leer.
        for ($n = 1; $n <= 4; $n++) {
            $plan->leaseStages()->create([
                'stage_no' => $n,
                'start_year' => $n === 1 ? $plan->year_from : null,
                'start_month' => 1,
                'rate_factor_pct' => 100,
                'festpacht_monthly' => 0,
            ]);
        }

        // Kapitalbedarf-Positionen (Finanzierung).
        foreach (self::FINANCINGS as $i => [$label, $type]) {
            $plan->financings()->create([
                'label' => $label,
                'finance_type' => $type,
                'amount' => 0,
                'sort_order' => $i,
            ]);
        }

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

    /**
     * Legt für bestehende Pläne ohne Pacht-Stufen vier Stufen an und übernimmt
     * dabei die alten Einzelfelder (Umsatzpacht-Start, Festpacht). Idempotent.
     */
    public function ensureLeaseStages(BusinessPlan $plan): void
    {
        if ($plan->leaseStages()->exists()) {
            return;
        }

        $upYear = $plan->umsatzpacht_start_year ?: $plan->year_from;
        $upMonth = (int) ($plan->umsatzpacht_start_month ?: 1);
        $festMonthly = (float) $plan->festpacht_monthly;
        $festYear = $plan->festpacht_start_year ?: $upYear;
        $festMonth = (int) ($plan->festpacht_start_month ?: 1);
        $sameStart = ($festYear === $upYear && $festMonth === $upMonth);

        $stages = [[
            'start_year' => $upYear, 'start_month' => $upMonth,
            'rate_factor_pct' => 100,
            'festpacht_monthly' => ($festMonthly > 0 && $sameStart) ? $festMonthly : 0,
        ]];
        if ($festMonthly > 0 && ! $sameStart) {
            $stages[] = [
                'start_year' => $festYear, 'start_month' => $festMonth,
                'rate_factor_pct' => 100, 'festpacht_monthly' => $festMonthly,
            ];
        }

        $n = 1;
        foreach ($stages as $st) {
            $plan->leaseStages()->create(array_merge(['stage_no' => $n++], $st));
        }
        while ($n <= 4) {
            $plan->leaseStages()->create([
                'stage_no' => $n++, 'start_year' => null, 'start_month' => 1,
                'rate_factor_pct' => 100, 'festpacht_monthly' => 0,
            ]);
        }
    }

    /**
     * Ergänzt fehlende Lohnbereiche (z. B. Werkstatt/Gastro) bei bereits
     * bestehenden Plänen, ohne vorhandene Zeilen zu verändern. Idempotent.
     */
    public function ensureStaffAreas(BusinessPlan $plan): void
    {
        $existing = $plan->staffLines()->pluck('area')->unique()->all();
        $years = $plan->years();
        $sort = (int) $plan->staffLines()->max('sort_order');

        foreach (self::STAFF as [$label, $group, $deduction, $area]) {
            if (in_array($area, $existing, true)) {
                continue;   // Bereich ist bereits vorhanden -> nicht doppelt anlegen
            }
            $line = $plan->staffLines()->create([
                'area' => $area,
                'category' => $group,
                'label' => $label,
                'is_deduction' => $deduction,
                'sort_order' => ++$sort,
            ]);
            foreach ($years as $year) {
                $line->values()->create([
                    'year' => $year, 'hours_per_day' => 0, 'days_per_week' => 0, 'hourly_wage' => 0,
                ]);
            }
        }
    }
}
