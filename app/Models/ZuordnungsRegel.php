<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lernfähige Zuordnungsregel (Modul 4). Bildet Muster (z. B. "HBW") auf
 * Lieferant/Kategorie/Kostenstelle/Kontierung ab. Jede Bestätigung erhöht
 * 'treffer_anzahl' und damit das Gewicht der Regel.
 */
class ZuordnungsRegel extends Model
{
    protected $table = 'zuordnungs_regeln';

    protected $guarded = [];

    protected $casts = [
        'prioritaet' => 'integer',
        'treffer_anzahl' => 'integer',
        'aktiv' => 'boolean',
    ];

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class);
    }

    public function kostenstelle(): BelongsTo
    {
        return $this->belongsTo(Kostenstelle::class);
    }

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
    }

    public function trefferZaehlen(): void
    {
        $this->increment('treffer_anzahl');
    }

    public function scopeAktiv($query)
    {
        return $query->where('aktiv', true);
    }
}
