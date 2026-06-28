<?php

namespace App\Services\Plan;

/**
 * Berechnet das Personalkostenbudget je Jahr aus den Schicht-/Lohnzeilen,
 * angelehnt an die Aral/GP-OIL-Personalkostenplanung:
 *
 *   Lohn p.a. je Zeile = Std/Tag × Tage/Woche × 52 × Stundenlohn
 *   Lohnkosten         = Summe der Löhne (Eigenanteil Unternehmer wird abgezogen)
 *   Urlaub/Krankheit   = Aufschlag in % auf die Lohnkosten
 *   AG-Anteil          = gewichteter Satz aus Festangestellten (Anteil %) und
 *                        Aushilfen auf (Lohnkosten + Urlaub)
 *   Zuschläge          = Sonntags-/Feiertags-/Nachtstunden × Ø-Lohn × Zuschlag %
 *   Personalkostenbudget = Lohnkosten + Urlaub + AG-Anteil + Zuschläge
 */
class PayrollCalculator
{
    private const WEEKS = 52;

    /**
     * @param  array<int, list<array{hours_per_day: float, days_per_week: float, hourly_wage: float, is_deduction: bool}>>  $rowsByYear
     * @param  array{vacation_pct: float, fest_pct: float, ag_fest: float, ag_aushilfe: float, sonntag_hours: float, sonntag_pct: float, feiertag_hours: float, feiertag_pct: float, nacht_hours: float, nacht_pct: float}  $a
     * @return array<int, array{hours: float, lohnkosten: float, urlaub: float, ag_anteil: float, zuschlaege: float, nebenkosten: float, budget: float}>
     */
    public static function compute(array $rowsByYear, array $a): array
    {
        $festShare = (float) $a['fest_pct'] / 100;
        $blendedAg = $festShare * (float) $a['ag_fest'] / 100
            + (1 - $festShare) * (float) $a['ag_aushilfe'] / 100;

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

            $urlaub = $lohnkosten * (float) $a['vacation_pct'] / 100;
            $agAnteil = ($lohnkosten + $urlaub) * $blendedAg;

            // Zuschläge auf Basis des durchschnittlichen Stundenlohns.
            $avgWage = $hours > 0 ? $lohnkosten / $hours : 0.0;
            $zuschlaege = $avgWage * (
                (float) $a['sonntag_hours'] * (float) $a['sonntag_pct'] / 100
                + (float) $a['feiertag_hours'] * (float) $a['feiertag_pct'] / 100
                + (float) $a['nacht_hours'] * (float) $a['nacht_pct'] / 100
            );

            $nebenkosten = $agAnteil + $zuschlaege;

            $out[$year] = [
                'hours' => round($hours, 2),
                'lohnkosten' => round($lohnkosten, 2),
                'urlaub' => round($urlaub, 2),
                'ag_anteil' => round($agAnteil, 2),
                'zuschlaege' => round($zuschlaege, 2),
                'nebenkosten' => round($nebenkosten, 2),
                'budget' => round($lohnkosten + $urlaub + $nebenkosten, 2),
            ];
        }

        return $out;
    }
}
