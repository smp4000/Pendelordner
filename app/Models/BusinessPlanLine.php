<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Einzelne Plan-Position (Umsatz- oder Kostenzeile) eines Geschäftsplans. */
class BusinessPlanLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'has_margin' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(BusinessPlanLineValue::class);
    }
}
