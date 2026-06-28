<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Werte einer Lohnzeile für ein Jahr: Std/Tag, Tage/Woche, Stundenlohn. */
class BusinessPlanStaffValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'hours_per_day' => 'decimal:2',
        'days_per_week' => 'decimal:2',
        'hourly_wage' => 'decimal:2',
    ];

    public function line(): BelongsTo
    {
        return $this->belongsTo(BusinessPlanStaffLine::class, 'business_plan_staff_line_id');
    }
}
