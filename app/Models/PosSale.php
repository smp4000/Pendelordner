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
}
