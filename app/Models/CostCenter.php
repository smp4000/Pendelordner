<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Kostenstelle (Modul 9). */
class CostCenter extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }
}
