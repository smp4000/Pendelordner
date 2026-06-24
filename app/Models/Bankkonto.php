<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bankkonto extends Model
{
    use SoftDeletes;

    protected $table = 'bankkonten';

    protected $guarded = [];

    protected $casts = [
        'saldo' => 'decimal:2',
        'saldo_datum' => 'datetime',
        'fints_aktiv' => 'boolean',
        'aktiv' => 'boolean',
        'letzter_abruf_at' => 'datetime',
    ];

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
    }

    public function fintsZugang(): BelongsTo
    {
        return $this->belongsTo(FintsZugang::class, 'fints_zugang_id');
    }

    public function bankumsaetze(): HasMany
    {
        return $this->hasMany(Bankumsatz::class);
    }

    public function importProtokolle(): HasMany
    {
        return $this->hasMany(ImportProtokoll::class);
    }

    public function getAnzeigeNameAttribute(): string
    {
        return $this->bezeichnung . ($this->iban ? ' (' . $this->iban . ')' : '');
    }
}
