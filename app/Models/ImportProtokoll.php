<?php

namespace App\Models;

use App\Enums\ImportQuelle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportProtokoll extends Model
{
    protected $table = 'import_protokolle';

    protected $guarded = [];

    protected $casts = [
        'quelle' => ImportQuelle::class,
        'anzahl_gesamt' => 'integer',
        'anzahl_neu' => 'integer',
        'anzahl_dubletten' => 'integer',
        'anzahl_fehler' => 'integer',
        'gestartet_at' => 'datetime',
        'beendet_at' => 'datetime',
    ];

    public function bankkonto(): BelongsTo
    {
        return $this->belongsTo(Bankkonto::class);
    }

    public function bankumsaetze(): HasMany
    {
        return $this->hasMany(Bankumsatz::class);
    }
}
