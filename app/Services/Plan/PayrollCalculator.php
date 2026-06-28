<?php

namespace App\Services\Plan;

/**
 * Berechnet das Personalkostenbudget je Jahr aus den Schicht-/Lohnzeilen,
 * angelehnt an die Aral/GP-OIL-Personalkostenplanung:
 *
 *   Lohn p.a. je Zeile = Std/Tag × Tage/Woche × 52 × Stundenlohn
 *   Lohnkosten         = Summe der Löhne (Eigenanteil Unternehmer wird abgezogen)
 *   Urlaub/Krankheit   = Aufschlag in % auf die Lohnkosten
 *   Lohnnebenkosten    = AG-Anteil in % auf (Lohnkosten + Urlaub)
 *   Personalkostenbudget = Lohnkosten + Urlaub + Lohnnebenkosten
 */
class PayrollCalculator
{
    private const WEEKS = 52;

    /**
     * @param  array<int, list<array{hours_per_day: float, days_per_week: float, hourly_wage: float, is_deduction: bool}>>  $rowsByYear
     * @return array<int, array{hours: float, lohnkosten: float, urlaub: float, nebenkosten: float, budget: float}>
     */
    public static function compute(array $rowsByYear, float $overheadPct, float $vacationPct): array
    {
        $out = [];
        foreach ($rowsByYear as $year => $rows) {
            $hours = 0.0;
            $lohnkosten = 0.0;

            foreach ($rows as $r) {
                $h = (float) $r['hours_per_day'] * (float) $r['days_per_week'] * self::WEEKS;
                $lohn = $h * (float) $r['hourly_wage'];
                $sign = ! empty($r['is_deduction']) ? -1 : 1;
                $hours += $sign * $h;
                $lohnkosten += $sign * $lohn;
            }

            $urlaub = $lohnkosten * $vacationPct / 100;
            $nebenkosten = ($lohnkosten + $urlaub) * $overheadPct / 100;

            $out[$year] = [
                'hours' => round($hours, 2),
                'lohnkosten' => round($lohnkosten, 2),
                'urlaub' => round($urlaub, 2),
                'nebenkosten' => round($nebenkosten, 2),
                'budget' => round($lohnkosten + $urlaub + $nebenkosten, 2),
            ];
        }

        return $out;
    }
}
