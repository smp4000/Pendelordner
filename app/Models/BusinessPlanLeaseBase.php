<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bemessungsgrundlage der Shopumsatzpacht (Umsatzquelle + Satz %). */
class BusinessPlanLeaseBase extends Model
{
    protected $guarded = [];

    protected $casts = [
        'manual_amount' => 'decimal:2',
        'rate_pct' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    public function businessPlan(): BelongsTo
    {
        return $this->belongsTo(BusinessPlan::class);
    }
}
