<?php

namespace App\Models;

use App\Enums\Kontenrahmen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DATEV-Export-Kopf (Modul 14 – nur Vorbereitung).
 */
class DatevExport extends Model
{
    protected $table = 'datev_exporte';

    protected $guarded = [];

    protected $casts = [
        'von_datum' => 'date',
        'bis_datum' => 'date',
        'kontenrahmen' => Kontenrahmen::class,
        'sachkontenlaenge' => 'integer',
        'anzahl_buchungen' => 'integer',
    ];

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
    }

    public function kontierungen(): HasMany
    {
        return $this->hasMany(Kontierung::class);
    }
}
