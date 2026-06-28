<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pacht-Stufe: Startzeitpunkt, Satz-Faktor % und Festpacht €/Monat. */
class BusinessPlanLeaseStage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'stage_no' => 'integer',
        'start_year' => 'integer',
        'start_month' => 'integer',
        'rate_factor_pct' => 'decimal:2',
        'festpacht_monthly' => 'decimal:2',
    ];

    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
