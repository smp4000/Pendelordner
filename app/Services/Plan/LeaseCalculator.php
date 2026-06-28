<?php

namespace App\Services\Plan;

/**
 * Berechnet die Stationspacht je Jahr:
 *   Shopumsatzpacht = Summe (Bemessungs-Umsatz × Satz %), anteilig ab Startmonat
 *   Festpacht       = €/Monat × aktive Monate ab Startstufe
 *   Pacht gesamt    = Shopumsatzpacht + Festpacht
 */
class LeaseCalculator
{
    /**
     * @param  array<int, list<array{amount: float, rate: float}>>  $basesByYear  je Jahr die Bemessungsgrundlagen
     * @param  array{up_start_year: int|null, up_start_month: int, fest_monthly: float, fest_start_year: int|null, fest_start_month: int}  $s
     * @return array<int, array{umsatzpacht: float, festpacht: float, total: float}>
     */
    public static function compute(array $basesByYear, array $s): array
    {
        $out = [];
        foreach ($basesByYear as $year => $bases) {
            $upFull = 0.0;
            foreach ($bases as $b) {
                $upFull += (float) $b['amount'] * (float) $b['rate'] / 100;
            }

            $upMonths = self::activeMonths($year, $s['up_start_year'], (int) $s['up_start_month']);
            $umsatzpacht = $upFull * $upMonths / 12;

            $fpMonths = self::activeMonths($year, $s['fest_start_year'], (int) $s['fest_start_month']);
            $festpacht = (float) $s['fest_monthly'] * $fpMonths;

            $out[$year] = [
                'umsatzpacht' => round($umsatzpacht, 2),
                'festpacht' => round($festpacht, 2),
                'total' => round($umsatzpacht + $festpacht, 2),
            ];
        }

        return $out;
    }

    /** Anzahl aktiver Monate eines Jahres ab (startYear, startMonth). */
    private static function activeMonths(int $year, ?int $startYear, int $startMonth): int
    {
        if ($startYear === null) {
            return 12;            // kein Start gesetzt -> ganzjährig
        }
        if ($year < $startYear) {
            return 0;
        }
        if ($year > $startYear) {
            return 12;
        }

        return max(0, 12 - max(1, $startMonth) + 1);
    }
}
