<?php

namespace App\Models;

use App\Enums\ImportSource;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bankumsatz (Modul 2/5/6) – zentrale Entität. Ein Umsatz kann beliebig viele
 * Belege enthalten; der Status ergibt sich aus der Summe der zugeordneten
 * Belegbeträge im Verhältnis zum Umsatzbetrag.
 */
class BankTransaction extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'booking_date' => 'date',
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'status' => TransactionStatus::class,
        'import_source' => ImportSource::class,
        'reviewed' => 'boolean',
        'fully_paid' => 'boolean',
    ];

    // ---- Beziehungen -------------------------------------------------------

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function importLog(): BelongsTo
    {
        return $this->belongsTo(ImportLog::class);
    }

    public function receipts(): BelongsToMany
    {
        return $this->belongsToMany(Receipt::class)
            ->withPivot(['amount', 'match_type', 'match_score', 'note'])
            ->withTimestamps();
    }

    public function costCenters(): BelongsToMany
    {
        return $this->belongsToMany(CostCenter::class)
            ->withPivot(['amount', 'percentage'])
            ->withTimestamps();
    }

    public function accountAssignments(): MorphMany
    {
        return $this->morphMany(AccountAssignment::class, 'assignable');
    }

    // ---- Berechnete Attribute ---------------------------------------------

    /** Summe der zugeordneten Belegbeträge (Pivot-Betrag). */
    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->receipts->sum(fn (Receipt $r) => (float) $r->pivot->amount);
    }

    /** Differenz zwischen Umsatzbetrag (absolut) und zugeordneter Belegsumme. */
    public function getDifferenceAttribute(): float
    {
        return round(abs((float) $this->amount) - $this->allocated_amount, 2);
    }

    public function getIsFullyAllocatedAttribute(): bool
    {
        return abs($this->difference) < 0.01 && $this->receipts->isNotEmpty();
    }

    // ---- Status-Workflow ---------------------------------------------------

    /** Status anhand der aktuellen Belegzuordnung neu berechnen. */
    public function recalculateStatus(bool $persist = true): TransactionStatus
    {
        $this->loadMissing('receipts');

        $status = match (true) {
            $this->reviewed => TransactionStatus::Reviewed,
            $this->is_fully_allocated => TransactionStatus::FullyAllocated,
            $this->receipts->isNotEmpty() => TransactionStatus::PartiallyAllocated,
            default => TransactionStatus::Open,
        };

        $this->status = $status;

        if ($persist) {
            $this->saveQuietly();
        }

        return $status;
    }

    // ---- Scopes ------------------------------------------------------------

    public function scopeWithoutReceipt(Builder $query): Builder
    {
        return $query->whereDoesntHave('receipts');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            TransactionStatus::Open->value,
            TransactionStatus::PartiallyAllocated->value,
        ]);
    }

    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('amount', '>', 0);
    }
}
