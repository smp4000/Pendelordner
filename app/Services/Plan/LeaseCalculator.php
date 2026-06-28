<?php

namespace App\Services\Plan;

/**
 * Berechnet die Stationspacht je Jahr monatsgenau mit Staffelung:
 *   - aktiv ist je Monat die Stufe mit dem spätesten Start ≤ (Jahr, Monat)
 *   - Shopumsatzpacht/Monat = Σ (Bemessungs-Umsatz/12 × Satz % × Stufen-Faktor %)
 *   - Festpacht/Monat       = Festpacht-Betrag der aktiven Stufe
 *   - vor der ersten Stufe fällt keine Pacht an
 */
class LeaseCalculator
{
    /**
     * @param  array<int, list<array{amount: float, rate: float}>>  $basesByYear  je Jahr die Bemessungsgrundlagen
     * @param  list<array{start_year: int, start_month: int, rate_factor: float, festpacht: float}>  $stages  nur Stufen mit gesetztem Start
     * @return array<int, array{umsatzpacht: float, festpacht: float, total: float}>
     */
    public static function compute(array $basesByYear, array $stages): array
    {
        // Stufen nach Startzeitpunkt sortieren.
        usort($stages, fn ($a, $b) => [$a['start_year'], $a['start_month']] <=> [$b['start_year'], $b['start_month']]);

        $out = [];
        foreach ($basesByYear as $year => $bases) {
            $umsatzpacht = 0.0;
            $festpacht = 0.0;

            // Voller Monatswert der Umsatzpacht bei Faktor 100 %.
            $fullMonth = 0.0;
            foreach ($bases as $b) {
                $fullMonth += (float) $b['amount'] / 12 * (float) $b['rate'] / 100;
            }

            for ($m = 1; $m <= 12; $m++) {
                $stage = self::activeStage($stages, $year, $m);
                if ($stage === null) {
                    continue;
                }
                $umsatzpacht += $fullMonth * (float) $stage['rate_factor'] / 100;
                $festpacht += (float) $stage['festpacht'];
            }

            $out[$year] = [
                'umsatzpacht' => round($umsatzpacht, 2),
                'festpacht' => round($festpacht, 2),
                'total' => round($umsatzpacht + $festpacht, 2),
            ];
        }

        return $out;
    }

    /** Die im Monat aktive Stufe (späteste mit Start ≤ Jahr/Monat) oder null. */
    private static function activeStage(array $stages, int $year, int $month): ?array
    {
        $active = null;
        foreach ($stages as $s) {
            if ($s['start_year'] < $year || ($s['start_year'] === $year && $s['start_month'] <= $month)) {
                $active = $s;   // Stufen sind aufsteigend sortiert -> letzte passende gewinnt
            }
        }

        return $active;
    }
}
