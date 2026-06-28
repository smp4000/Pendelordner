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
        'payroll_overhead_pct' => 'decimal:2',
        'vacation_pct' => 'decimal:2',
        'umsatzpacht_start_year' => 'integer',
        'umsatzpacht_start_month' => 'integer',
        'festpacht_monthly' => 'decimal:2',
        'festpacht_start_year' => 'integer',
        'festpacht_start_month' => 'integer',
        'interest_rate' => 'decimal:3',
        'gewst_enabled' => 'boolean',
        'gewst_hebesatz' => 'decimal:2',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BusinessPlanLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function staffLines(): HasMany
    {
        return $this->hasMany(BusinessPlanStaffLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function leaseBases(): HasMany
    {
        return $this->hasMany(BusinessPlanLeaseBase::class)->orderBy('sort_order')->orderBy('id');
    }

    public function financings(): HasMany
    {
        return $this->hasMany(BusinessPlanFinancing::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Kapitalbedarf = Summe der Finanzierungspositionen (= Darlehensbetrag). */
    public function capitalNeed(): float
    {
        return (float) $this->financings->sum(fn ($f) => (float) $f->amount);
    }

    /**
     * Jährliche Zinsen auf das Darlehen (Restschuld zu Jahresbeginn × Zinssatz).
     *
     * @return array<int, float>
     */
    public function interestByYear(): array
    {
        return \App\Services\Plan\FinanceCalculator::interestByYear(
            $this->capitalNeed(),
            (float) $this->annual_repayment,
            (float) $this->interest_rate,
            $this->years(),
        );
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

            $gewinn = $rohertrag - $kosten;
            $tax = \App\Services\Plan\TaxCalculator::gewst($gewinn, (float) $this->gewst_hebesatz, (bool) $this->gewst_enabled);

            $out[$year] = [
                'umsatz' => round($umsatz, 2),
                'rohertrag' => round($rohertrag, 2),
                'kosten' => round($kosten, 2),
                'gewinn' => round($gewinn, 2),
                'gewst' => $tax['gewst'],
                'gewst_na' => $tax['nicht_anrechenbar'],
                'gewinn_nach_steuern' => round($gewinn - $tax['nicht_anrechenbar'], 2),
            ];
        }

        return $out;
    }

    /**
     * Personalkostenberechnung (Lohnberechnung) je Jahr aus den Schichtzeilen.
     *
     * @return array<int, array{hours: float, lohnkosten: float, urlaub: float, nebenkosten: float, budget: float}>
     */
    public function payroll(): array
    {
        $rowsByYear = [];
        foreach ($this->years() as $year) {
            $rows = [];
            foreach ($this->staffLines as $line) {
                $v = $line->values->firstWhere('year', $year);
                $rows[] = [
                    'hours_per_day' => (float) ($v->hours_per_day ?? 0),
                    'days_per_week' => (float) ($v->days_per_week ?? 0),
                    'hourly_wage' => (float) ($v->hourly_wage ?? 0),
                    'is_deduction' => (bool) $line->is_deduction,
                ];
            }
            $rowsByYear[$year] = $rows;
        }

        return \App\Services\Plan\PayrollCalculator::compute(
            $rowsByYear,
            (float) $this->payroll_overhead_pct,
            (float) $this->vacation_pct,
        );
    }

    /** Umsatz einer Umsatzzeile (per Bezeichnung) in einem Jahr. */
    private function revenueAmount(string $label, int $year): float
    {
        $line = $this->lines->first(fn ($l) => $l->section === 'revenue' && $l->label === $label);
        $v = $line?->values->firstWhere('year', $year);

        return (float) ($v->amount ?? 0);
    }

    /** Summe einer Umsatzgruppe (category) in einem Jahr. */
    private function groupAmount(string $category, int $year): float
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            if ($line->section === 'revenue' && $line->category === $category) {
                $v = $line->values->firstWhere('year', $year);
                $sum += (float) ($v->amount ?? 0);
            }
        }

        return $sum;
    }

    /** Bemessungs-Umsatz einer Pacht-Grundlage je Jahr (aus dem Umsatzplan oder manuell). */
    public function leaseBaseAmount(BusinessPlanLeaseBase $base, int $year): float
    {
        return match ($base->source) {
            'tabak' => $this->revenueAmount('Tabakwaren', $year),
            'wasch' => $this->revenueAmount('Autowaschanlage', $year),
            'shop_rest' => max(0.0, $this->groupAmount('Shop / Bistro', $year)
                - $this->revenueAmount('Tabakwaren', $year)
                - $this->revenueAmount('Karten, Bücher, Zeitschriften', $year)),
            default => (float) $base->manual_amount,
        };
    }

    /**
     * Pachtberechnung je Jahr (Shopumsatzpacht + Festpacht).
     *
     * @return array<int, array{umsatzpacht: float, festpacht: float, total: float}>
     */
    public function lease(): array
    {
        $basesByYear = [];
        foreach ($this->years() as $year) {
            $rows = [];
            foreach ($this->leaseBases as $base) {
                $rows[] = ['amount' => $this->leaseBaseAmount($base, $year), 'rate' => (float) $base->rate_pct];
            }
            $basesByYear[$year] = $rows;
        }

        return \App\Services\Plan\LeaseCalculator::compute($basesByYear, [
            'up_start_year' => $this->umsatzpacht_start_year,
            'up_start_month' => (int) ($this->umsatzpacht_start_month ?: 1),
            'fest_monthly' => (float) $this->festpacht_monthly,
            'fest_start_year' => $this->festpacht_start_year,
            'fest_start_month' => (int) ($this->festpacht_start_month ?: 1),
        ]);
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
                'gewst' => $overview[$year]['gewst'] ?? 0,
            ];
        }

        return \App\Services\Plan\LiquidityCalculator::compute($perYear, [
            'vat_rate' => (float) $this->vat_rate,
            'loan_amount' => $this->capitalNeed(),
            'annual_repayment' => (float) $this->annual_repayment,
            'private_draw' => (float) $this->private_draw,
            'opening_balance' => (float) $this->opening_balance,
        ]);
    }
}
