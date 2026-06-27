<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Hinweis-Karte an das Steuerbüro je Bankkonto und Monat (Modul 12). */
class ReportNote extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period' => 'date',
        'sort_order' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReportNoteLine::class)->orderBy('sort_order');
    }
}
