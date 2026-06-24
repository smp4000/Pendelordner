<?php

namespace App\Models;

use App\Enums\BelegStatus;
use App\Enums\BelegTyp;
use App\Enums\OcrStatus;
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
class Beleg extends Model
{
    use SoftDeletes;

    protected $table = 'belege';

    protected $guarded = [];

    protected $casts = [
        'typ' => BelegTyp::class,
        'status' => BelegStatus::class,
        'ocr_status' => OcrStatus::class,
        'rechnungsdatum' => 'date',
        'leistungsdatum' => 'date',
        'faellig_am' => 'date',
        'betrag_brutto' => 'decimal:2',
        'betrag_netto' => 'decimal:2',
        'steuerbetrag' => 'decimal:2',
        'steuersatz' => 'decimal:2',
        'datei_groesse' => 'integer',
        'bezahlt' => 'boolean',
        'geprueft' => 'boolean',
        'ocr_durchgefuehrt_at' => 'datetime',
    ];

    // ---- Beziehungen -------------------------------------------------------

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
    }

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

    public function bankumsaetze(): BelongsToMany
    {
        return $this->belongsToMany(Bankumsatz::class, 'beleg_bankumsatz')
            ->withPivot(['betrag', 'zuordnungs_art', 'trefferquote', 'notiz'])
            ->withTimestamps();
    }

    public function kostenstellen(): BelongsToMany
    {
        return $this->belongsToMany(Kostenstelle::class, 'beleg_kostenstelle')
            ->withPivot(['betrag', 'anteil_prozent'])
            ->withTimestamps();
    }

    public function kontierungen(): MorphMany
    {
        return $this->morphMany(Kontierung::class, 'kontierbar');
    }

    // ---- Berechnete Attribute ---------------------------------------------

    /** Bereits einem Umsatz zugeordneter Betrag (Summe der Pivot-Beträge). */
    public function getZugeordneteSummeAttribute(): float
    {
        return (float) $this->bankumsaetze->sum(fn (Bankumsatz $u) => (float) $u->pivot->betrag);
    }

    /** Noch nicht zugeordneter Restbetrag des Belegs. */
    public function getOffenerBetragAttribute(): float
    {
        return round((float) ($this->betrag_brutto ?? 0) - $this->zugeordnete_summe, 2);
    }

    public function getOeffentlicheUrlAttribute(): ?string
    {
        if (! $this->datei_pfad) {
            return null;
        }

        return Storage::disk(config('pendelordner.belege_disk', 'belege'))->url($this->datei_pfad);
    }

    public function getIstPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    // ---- Scopes ------------------------------------------------------------

    public function scopeOhneZahlung($query)
    {
        return $query->where('bezahlt', false);
    }

    public function scopeNichtZugeordnet($query)
    {
        return $query->whereDoesntHave('bankumsaetze');
    }

    public function scopeOcrAusstehend($query)
    {
        return $query->where('ocr_status', OcrStatus::Ausstehend->value);
    }
}
