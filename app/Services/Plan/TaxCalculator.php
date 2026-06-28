<?php

namespace App\Services\Plan;

/**
 * Gewerbesteuer für Einzelunternehmen/Personengesellschaften:
 *   Messbetrag = max(0, Gewinn − Freibetrag) × 3,5 %
 *   GewSt      = Messbetrag × Hebesatz %
 *   anrechenbar (§35 EStG) = 3,8 × Messbetrag (gedeckelt auf GewSt)
 *   nicht anrechenbar = GewSt − anrechenbar
 * Der steuerrechtliche Gewinn (nach Steuern) wird um die nicht anrechenbare
 * Gewerbesteuer gemindert; der anrechenbare Teil mindert die Einkommensteuer.
 */
class TaxCalculator
{
    public const FREIBETRAG = 24500.0;

    private const MESSZAHL = 0.035;

    private const ANRECHNUNG = 3.8;

    /**
     * @return array{messbetrag: float, gewst: float, nicht_anrechenbar: float}
     */
    public static function gewst(float $gewinn, float $hebesatzPct, bool $enabled): array
    {
        if (! $enabled || $hebesatzPct <= 0 || $gewinn <= self::FREIBETRAG) {
            return ['messbetrag' => 0.0, 'gewst' => 0.0, 'nicht_anrechenbar' => 0.0];
        }

        $messbetrag = ($gewinn - self::FREIBETRAG) * self::MESSZAHL;
        $gewst = $messbetrag * $hebesatzPct / 100;
        $anrechenbar = min($gewst, self::ANRECHNUNG * $messbetrag);
        $nichtAnrechenbar = max(0.0, $gewst - $anrechenbar);

        return [
            'messbetrag' => round($messbetrag, 2),
            'gewst' => round($gewst, 2),
            'nicht_anrechenbar' => round($nichtAnrechenbar, 2),
        ];
    }
}
