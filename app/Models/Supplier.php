<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Lieferant / Kreditor (Modul 4/14). */
class Supplier extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function defaultCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'default_cost_center_id');
    }

    public function defaultBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'default_business_id');
    }

    public function bankTransactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function matchingRules(): HasMany
    {
        return $this->hasMany(MatchingRule::class);
    }

    /** Verknüpfungen zu Tankstellen (Betrieben) inkl. Kundennummer. */
    public function customerNumbers(): HasMany
    {
        return $this->hasMany(SupplierCustomerNumber::class);
    }

    /** Tankstellen (Betriebe), die diesem Lieferanten zugeordnet sind. */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'supplier_customer_numbers')
            ->withPivot(['customer_number', 'note'])
            ->withTimestamps();
    }
}
