<?php

namespace App\Models;

use App\Enums\ChartOfAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Kontierung (Modul 13 – Vorbereitung). Polymorph an Bankumsatz oder Beleg. */
class AccountAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'chart_of_accounts' => ChartOfAccounts::class,
        'service_date' => 'date',
        'booking_date' => 'date',
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'exported' => 'boolean',
    ];

    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function datevExport(): BelongsTo
    {
        return $this->belongsTo(DatevExport::class);
    }
}
