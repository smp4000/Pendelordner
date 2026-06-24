<?php

namespace App\Models;

use App\Enums\Kontenrahmen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Kontierung (Modul 13 – Vorbereitung). Polymorph an Bankumsatz oder Beleg.
 */
class Kontierung extends Model
{
    protected $table = 'kontierungen';

    protected $guarded = [];

    protected $casts = [
        'kontenrahmen' => Kontenrahmen::class,
        'leistungsdatum' => 'date',
        'buchungsdatum' => 'date',
        'betrag' => 'decimal:2',
        'exportiert' => 'boolean',
    ];

    public function kontierbar(): MorphTo
    {
        return $this->morphTo();
    }

    public function kostenstelle(): BelongsTo
    {
        return $this->belongsTo(Kostenstelle::class);
    }

    public function datevExport(): BelongsTo
    {
        return $this->belongsTo(DatevExport::class);
    }
}
