<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Geschäftsplan einer Tankstelle (mehrjährig). Die Geschäftsplanübersicht
 * (Umsatz, Rohertrag, Kosten, Gewinn je Jahr) wird aus den Positionen
 * berechnet – siehe overview().
 */
class BusinessPlan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'year_from' => 'integer',
        'year_to' => 'integer',
        'loan_amount' => 'decimal:2',
        'private_draw' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'annual_repayment' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BusinessPlanLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return list<int> */
    public function years(): array
    {
        return range($this->year_from, max($this->year_from, $this->year_to));
    }

    /**
     * Geschäftsplanübersicht je Jahr.
     *
     * @return array<int, array{umsatz: float, rohertrag: float, kosten: float, gewinn: float}>
     */
    public function overview(): array
    {
        $out = [];
        foreach ($this->years() as $year) {
            $umsatz = 0.0;
            $rohertrag = 0.0;
            $kosten = 0.0;

            foreach ($this->lines as $line) {
                $value = $line->values->firstWhere('year', $year);
                $amount = (float) ($value->amount ?? 0);

                if ($line->section === 'revenue') {
                    $umsatz += $amount;
                    $rohertrag += $amount * ((float) ($value->margin ?? 0)) / 100;
                } else {
                    $kosten += $amount;
                }
            }

            $out[$year] = [
                'umsatz' => round($umsatz, 2),
                'rohertrag' => round($rohertrag, 2),
                'kosten' => round($kosten, 2),
                'gewinn' => round($rohertrag - $kosten, 2),
            ];
        }

        return $out;
    }

    /** Summe der Kostenzeilen eines Jahres, deren Bezeichnung mit „Personalkosten" beginnt. */
    private function personnelCosts(int $year): float
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            if ($line->section === 'cost' && str_starts_with($line->label, 'Personalkosten')) {
                $value = $line->values->firstWhere('year', $year);
                $sum += (float) ($value->amount ?? 0);
            }
        }

        return $sum;
    }

    /**
     * Liquiditätsplanung je Jahr und Monat (vereinfachtes, transparentes Modell).
     *
     * Annahmen: gleichmäßige Verteilung auf 12 Monate; pauschaler USt-Satz;
     * Vorsteuer nur auf den Wareneinsatz (Umsatz ./. Rohertrag); USt-Zahllast
     * monatlich ohne Zeitversatz; Darlehen im ersten Monat des ersten Jahres;
     * Tilgung und Privatentnahme gleichmäßig über das Jahr.
     *
     * @return array<int, array{months: array<int, array<string, float>>, totals: array<string, float>, end: float, credit: float}>
     */
    public function liquidity(): array
    {
        $overview = $this->overview();
        $perYear = [];
        foreach ($this->years() as $year) {
            $perYear[$year] = [
                'umsatz' => $overview[$year]['umsatz'],
                'rohertrag' => $overview[$year]['rohertrag'],
                'kosten' => $overview[$year]['kosten'],
                'personal' => $this->personnelCosts($year),
            ];
        }

        return \App\Services\Plan\LiquidityCalculator::compute($perYear, [
            'vat_rate' => (float) $this->vat_rate,
            'loan_amount' => (float) $this->loan_amount,
            'annual_repayment' => (float) $this->annual_repayment,
            'private_draw' => (float) $this->private_draw,
            'opening_balance' => (float) $this->opening_balance,
        ]);
    }
}
