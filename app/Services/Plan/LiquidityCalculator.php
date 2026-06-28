<?php

namespace App\Services\Plan;

/**
 * Berechnet den Liquiditätsverlauf eines Geschäftsplans (Monatsübersicht je
 * Jahr). Vereinfachtes, transparentes Modell – siehe Annahmen in
 * BusinessPlan::liquidity().
 */
class LiquidityCalculator
{
    /**
     * @param  array<int, array{umsatz: float, rohertrag: float, kosten: float, personal: float}>  $perYear  in Jahres-Reihenfolge
     * @param  array{vat_rate: float, loan_amount: float, annual_repayment: float, private_draw: float, opening_balance: float}  $a
     * @return array<int, array{months: array<int, array<string, float>>, totals: array<string, float>, end: float, credit: float}>
     */
    public static function compute(array $perYear, array $a): array
    {
        $vat = (float) $a['vat_rate'] / 100;
        $stand = (float) $a['opening_balance'];
        $credit = 0.0;
        $first = array_key_first($perYear);

        $out = [];
        foreach ($perYear as $year => $fig) {
            $umsatz = (float) $fig['umsatz'];
            $rohertrag = (float) $fig['rohertrag'];
            $wareneinsatz = max(0.0, $umsatz - $rohertrag);
            $personal = (float) $fig['personal'];
            $sonstige = max(0.0, (float) $fig['kosten'] - $personal);

            $ustUmsatz = $umsatz * $vat;
            $vstWare = $wareneinsatz * $vat;
            $zahllast = $ustUmsatz - $vstWare;

            $mEinnahmen = ($umsatz + $ustUmsatz) / 12;
            $mWare = ($wareneinsatz + $vstWare) / 12;
            $mPersonal = $personal / 12;
            $mSonstige = $sonstige / 12;
            $mUst = $zahllast / 12;
            $mTilgung = (float) $a['annual_repayment'] / 12;
            $mPrivat = (float) $a['private_draw'] / 12;

            $months = [];
            $sum = ['einnahmen' => 0.0, 'darlehen' => 0.0, 'ware' => 0.0, 'personal' => 0.0,
                'sonstige' => 0.0, 'ust' => 0.0, 'tilgung' => 0.0, 'privat' => 0.0, 'saldo' => 0.0];

            for ($m = 1; $m <= 12; $m++) {
                $darlehen = ($year === $first && $m === 1) ? (float) $a['loan_amount'] : 0.0;
                if ($darlehen > 0) {
                    $credit += $darlehen;
                }
                $tilgung = min($mTilgung, $credit);
                $credit -= $tilgung;

                $saldo = $mEinnahmen + $darlehen - $mWare - $mPersonal - $mSonstige - $mUst - $tilgung - $mPrivat;
                $stand += $saldo;

                $row = [
                    'einnahmen' => $mEinnahmen, 'darlehen' => $darlehen, 'ware' => $mWare,
                    'personal' => $mPersonal, 'sonstige' => $mSonstige, 'ust' => $mUst,
                    'tilgung' => $tilgung, 'privat' => $mPrivat, 'saldo' => $saldo,
                    'stand' => $stand, 'kredit' => $credit,
                ];
                $months[$m] = $row;
                foreach ($sum as $k => $v) {
                    $sum[$k] += $row[$k];
                }
            }

            $out[$year] = ['months' => $months, 'totals' => $sum, 'end' => $stand, 'credit' => $credit];
        }

        return $out;
    }
}
