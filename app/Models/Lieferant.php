<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lieferant extends Model
{
    use SoftDeletes;

    protected $table = 'lieferanten';

    protected $guarded = [];

    protected $casts = [
        'aktiv' => 'boolean',
    ];

    public function standardKategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'standard_kategorie_id');
    }

    public function standardKostenstelle(): BelongsTo
    {
        return $this->belongsTo(Kostenstelle::class, 'standard_kostenstelle_id');
    }

    public function standardBetrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class, 'standard_betrieb_id');
    }

    public function bankumsaetze(): HasMany
    {
        return $this->hasMany(Bankumsatz::class);
    }

    public function belege(): HasMany
    {
        return $this->hasMany(Beleg::class);
    }

    public function zuordnungsRegeln(): HasMany
    {
        return $this->hasMany(ZuordnungsRegel::class);
    }
}
