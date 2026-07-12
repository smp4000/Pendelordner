<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Wasch-/Abo-Zahlung aus dem Karten- oder PayPal-Export.
 * total = kassierter Bruttoerlös, tax = enthaltene USt (19 %).
 */
class WashPayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_source' => 'datetime',
        'payment_date' => 'date',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'application_fee' => 'decimal:2',
        'surcharge' => 'decimal:2',
        'state_code' => 'integer',
        'is_subscription' => 'boolean',
        'is_free' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** Nettoerlös (brutto minus enthaltene USt). */
    public function getNetAttribute(): float
    {
        return round((float) $this->total - (float) $this->tax, 2);
    }

    /**
     * Freiwäsche-Kategorie über das Kennzeichen (eigen/mitarbeiter/test) –
     * live aus der pflegbaren Kennzeichen-Liste, damit Änderungen sofort greifen.
     */
    public function getFreeCategoryAttribute(): ?string
    {
        if (! $this->plate_normalized) {
            return null;
        }

        return WashFreePlate::where('normalized', $this->plate_normalized)
            ->where('active', true)
            ->value('category');
    }
}
