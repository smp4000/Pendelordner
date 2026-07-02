<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kassenumsatz einer Artikelgruppe je Tankstelle und Monat. */
class PosSale extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period' => 'date',
        'fn' => 'integer',
        'quantity' => 'decimal:3',
        'amount_gross' => 'decimal:2',
        'is_fuel' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * USt-Satz je Artikelgruppe (Heuristik aus der Bezeichnung):
     * ermäßigt (7 %) bei „erm."/„EM", durchlaufend/neutral (0 %) bei Lotto/Toto,
     * „ohne MwSt", Kommission; sonst voll (19 %).
     */
    public function getTaxRateAttribute(): float
    {
        $g = mb_strtolower($this->article_group);

        if (str_contains($g, 'lotto') || str_contains($g, 'toto')
            || str_contains($g, 'ohne mwst') || str_contains($g, 'kommission')) {
            return 0.0;
        }
        if (str_contains($g, 'erm') || str_ends_with(rtrim($g), ' em')) {
            return 7.0;
        }

        return 19.0;
    }
}
