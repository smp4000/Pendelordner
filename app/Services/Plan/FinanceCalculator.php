<?php

namespace App\Services\Plan;

/** Zinsberechnung der Finanzierung (Restschuld zu Jahresbeginn × Zinssatz). */
class FinanceCalculator
{
    /**
     * @param  list<int>  $years
     * @return array<int, float>
     */
    public static function interestByYear(float $loan, float $repayment, float $ratePct, array $years): array
    {
        $out = [];
        $i = 0;
        foreach ($years as $year) {
            $outstanding = max(0.0, $loan - $repayment * $i);
            $out[$year] = round($outstanding * $ratePct / 100, 2);
            $i++;
        }

        return $out;
    }
}
