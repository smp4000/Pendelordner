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
}
