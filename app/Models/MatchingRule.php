<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lernfähige Zuordnungsregel (Modul 4). Bildet ein Muster (z. B. "HBW") auf
 * Lieferant/Kategorie/Kostenstelle/Kontierung ab. Jede Bestätigung erhöht
 * 'hit_count' und damit das Gewicht der Regel.
 */
class MatchingRule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'priority' => 'integer',
        'hit_count' => 'integer',
        'active' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function registerHit(): void
    {
        $this->increment('hit_count');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
