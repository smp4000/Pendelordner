<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Hochgeladene Datei zu den Steuerbüro-Hinweisen (je Konto, Monat, Kategorie). */
class SteuerDocument extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period' => 'date',
        'file_size' => 'integer',
        'sort_order' => 'integer',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->mime_type === 'application/pdf'
            || ($this->file_path && str_ends_with(strtolower($this->file_path), '.pdf'));
    }

    /** URL zur Inline-Vorschau der Datei (nutzt die Beleg-Route wiederverwendbar). */
    public function getPreviewUrlAttribute(): ?string
    {
        return $this->file_path ? route('steuer.datei', $this) : null;
    }
}
