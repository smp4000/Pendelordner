<?php

namespace App\Models;

use App\Enums\BankumsatzStatus;
use App\Enums\ImportQuelle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Zentrale Entität (Modul 2/5/6). Ein Bankumsatz kann beliebig viele Belege
 * enthalten; der Status ergibt sich aus der Summe der zugeordneten Belegbeträge
 * im Verhältnis zum Umsatzbetrag.
 */
class Bankumsatz extends Model
{
    use SoftDeletes;

    protected $table = 'bankumsaetze';

    protected $guarded = [];

    protected $casts = [
        'buchungsdatum' => 'date',
        'valutadatum' => 'date',
        'betrag' => 'decimal:2',
        'saldo_nach' => 'decimal:2',
        'status' => BankumsatzStatus::class,
        'import_quelle' => ImportQuelle::class,
        'geprueft' => 'boolean',
        'vollstaendig_bezahlt' => 'boolean',
    ];

    // ---- Beziehungen -------------------------------------------------------

    public function bankkonto(): BelongsTo
    {
        return $this->belongsTo(Bankkonto::class);
    }

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
    }

    public function kategorie(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class);
    }

    public function kostenstelle(): BelongsTo
    {
        return $this->belongsTo(Kostenstelle::class);
    }

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    public function importProtokoll(): BelongsTo
    {
        return $this->belongsTo(ImportProtokoll::class);
    }

    public function belege(): BelongsToMany
    {
        return $this->belongsToMany(Beleg::class, 'beleg_bankumsatz')
            ->withPivot(['betrag', 'zuordnungs_art', 'trefferquote', 'notiz'])
            ->withTimestamps();
    }

    public function kostenstellen(): BelongsToMany
    {
        return $this->belongsToMany(Kostenstelle::class, 'bankumsatz_kostenstelle')
            ->withPivot(['betrag', 'anteil_prozent'])
            ->withTimestamps();
    }

    public function kontierungen(): MorphMany
    {
        return $this->morphMany(Kontierung::class, 'kontierbar');
    }

    // ---- Berechnete Attribute ---------------------------------------------

    /** Summe der diesem Umsatz zugeordneten Belegbeträge (Pivot-Betrag). */
    public function getZugeordneteSummeAttribute(): float
    {
        return (float) $this->belege->sum(fn (Beleg $b) => (float) $b->pivot->betrag);
    }

    /** Differenz zwischen Umsatzbetrag (absolut) und zugeordneter Belegsumme. */
    public function getDifferenzAttribute(): float
    {
        return round(abs((float) $this->betrag) - $this->zugeordnete_summe, 2);
    }

    public function getIstVollstaendigAttribute(): bool
    {
        return abs($this->differenz) < 0.01 && $this->belege->isNotEmpty();
    }

    // ---- Status-Workflow ---------------------------------------------------

    /**
     * Status anhand der aktuellen Belegzuordnung neu berechnen.
     * Ein bereits gesetztes Prüf-Flag hat Vorrang (Status "Geprüft").
     */
    public function statusNeuBerechnen(bool $speichern = true): BankumsatzStatus
    {
        $this->loadMissing('belege');

        $status = match (true) {
            $this->geprueft => BankumsatzStatus::Geprueft,
            $this->ist_vollstaendig => BankumsatzStatus::VollstaendigZugeordnet,
            $this->belege->isNotEmpty() => BankumsatzStatus::TeilweiseZugeordnet,
            default => BankumsatzStatus::Offen,
        };

        $this->status = $status;

        if ($speichern) {
            $this->saveQuietly();
        }

        return $status;
    }

    // ---- Scopes ------------------------------------------------------------

    public function scopeOhneBeleg($query)
    {
        return $query->whereDoesntHave('belege');
    }

    public function scopeOffen($query)
    {
        return $query->whereIn('status', [
            BankumsatzStatus::Offen->value,
            BankumsatzStatus::TeilweiseZugeordnet->value,
        ]);
    }

    public function scopeAusgang($query)
    {
        return $query->where('betrag', '<', 0);
    }

    public function scopeEingang($query)
    {
        return $query->where('betrag', '>', 0);
    }
}
