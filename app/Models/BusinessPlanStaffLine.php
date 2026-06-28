<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Schicht-/Lohnzeile der Personalkostenberechnung eines Geschäftsplans. */
class BusinessPlanStaffLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_deduction' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(BusinessPlanStaffValue::class);
    }
}
