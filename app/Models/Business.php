<?php

namespace App\Models;

use App\Enums\BusinessType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Betrieb / Tankstelle (Modul 7). */
class Business extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'type' => BusinessType::class,
        'active' => 'boolean',
        'sort_order' => 'integer',
        'fuel_commission_ct' => 'decimal:3',
    ];

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class);
    }

    /** Eindeutiges Anzeige-Label (Name + Ort), da mehrere Betriebe gleich heißen können. */
    public function getDisplayLabelAttribute(): string
    {
        $location = trim(($this->postal_code ? $this->postal_code . ' ' : '') . ($this->city ?? ''));
        if ($location === '' && $this->short_name) {
            $location = $this->short_name;
        }

        return $location !== '' ? $this->name . ' – ' . $location : $this->name;
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
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
