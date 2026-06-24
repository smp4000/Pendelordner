<?php

namespace App\Models;

use App\Enums\ImportSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Import-Protokoll (Modul 1). */
class ImportLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'source' => ImportSource::class,
        'total_count' => 'integer',
        'new_count' => 'integer',
        'duplicate_count' => 'integer',
        'error_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }
}
