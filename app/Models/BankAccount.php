<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Bankkonto (Modul 1/2). */
class BankAccount extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'balance_date' => 'datetime',
        'fints_enabled' => 'boolean',
        'active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Ändert sich der Betrieb des Kontos, die Zuordnung auf alle Umsätze
        // des Kontos übertragen (sonst stimmt z. B. der Betriebsfilter im
        // Steuerberater-Bericht nicht mehr).
        static::updated(function (BankAccount $account): void {
            if ($account->wasChanged('business_id')) {
                $account->bankTransactions()->update(['business_id' => $account->business_id]);
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function fintsConnection(): BelongsTo
    {
        return $this->belongsTo(FintsConnection::class);
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label . ($this->iban ? ' (' . $this->iban . ')' : '');
    }
}
