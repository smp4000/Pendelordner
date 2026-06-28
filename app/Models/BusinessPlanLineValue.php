<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Wert einer Plan-Position für ein konkretes Jahr (Betrag + BVD-Marge). */
class BusinessPlanLineValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'amount' => 'decimal:2',
        'margin' => 'decimal:2',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(BusinessPlanLine::class, 'business_plan_line_id');
    }
}
