<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sachkonto eines Kontenrahmens (Modul 13). */
class LedgerAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'tax_rate' => 'decimal:2',
    ];

    public function getLabelAttribute(): string
    {
        return $this->number . ' – ' . $this->name;
    }

    /**
     * Leitet den USt-Satz aus dem Kontonamen ab, sofern er dort vermerkt ist:
     * "USt voll" = 19, "USt erm." = 7, "USt frei"/"steuerfrei"/"ohne USt" = 0.
     * Ist kein Hinweis enthalten, null (Satz muss am Konto gepflegt werden).
     */
    public static function deriveTaxRateFromName(?string $name): ?float
    {
        $n = mb_strtolower((string) $name);

        return match (true) {
            str_contains($n, 'ust voll') => 19.0,
            str_contains($n, 'ust erm') => 7.0,
            str_contains($n, 'ust frei'), str_contains($n, 'ust-frei'),
            str_contains($n, 'steuerfrei'), str_contains($n, 'ohne ust') => 0.0,
            default => null,
        };
    }
}
