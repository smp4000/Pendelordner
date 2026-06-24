<?php

namespace App\Models;

use App\Enums\ChartOfAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** DATEV-Export-Kopf (Modul 14 – nur Vorbereitung). */
class DatevExport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'chart_of_accounts' => ChartOfAccounts::class,
        'account_length' => 'integer',
        'entry_count' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function accountAssignments(): HasMany
    {
        return $this->hasMany(AccountAssignment::class);
    }
}
