<?php

namespace App\Models;

use App\Enums\OcrStatus;
use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Beleg / Rechnung (Modul 3). Wird per Upload erfasst, per OCR ausgewertet und
 * einem oder mehreren Bankumsätzen zugeordnet.
 */
class Receipt extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => ReceiptType::class,
        'status' => ReceiptStatus::class,
        'ocr_status' => OcrStatus::class,
        'invoice_date' => 'date',
        'service_date' => 'date',
        'due_date' => 'date',
        'gross_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'file_size' => 'integer',
        'paid' => 'boolean',
        'reviewed' => 'boolean',
        'include_in_report' => 'boolean',
        'ocr_processed_at' => 'datetime',
    ];

    // ---- Beziehungen -------------------------------------------------------

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function bankTransactions(): BelongsToMany
    {
        return $this->belongsToMany(BankTransaction::class)
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

    /** Bereits einem Umsatz zugeordneter Betrag (Summe der Pivot-Beträge). */
    public function getAllocatedAmountAttribute(): float
    {
        return (float) $this->bankTransactions->sum(fn (BankTransaction $t) => (float) $t->pivot->amount);
    }

    /** Noch nicht zugeordneter Restbetrag des Belegs. */
    public function getOpenAmountAttribute(): float
    {
        return round((float) ($this->gross_amount ?? 0) - $this->allocated_amount, 2);
    }

    public function getPublicUrlAttribute(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        return Storage::disk(config('pendelordner.belege_disk', 'belege'))->url($this->file_path);
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf'
            || ($this->file_path && str_ends_with(strtolower($this->file_path), '.pdf'));
    }

    /** URL zur Inline-Vorschau der Belegdatei (Modul 6). */
    public function getPreviewUrlAttribute(): ?string
    {
        return $this->file_path ? route('beleg.datei', $this) : null;
    }

    // ---- Scopes ------------------------------------------------------------

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->whereDoesntHave('bankTransactions');
    }

    public function scopeOcrPending(Builder $query): Builder
    {
        return $query->where('ocr_status', OcrStatus::Pending->value);
    }
}
