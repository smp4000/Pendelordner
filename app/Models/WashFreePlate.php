<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kennzeichen für Freiwäschen (eigen / mitarbeiter / test) – stationsübergreifend.
 */
class WashFreePlate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** Kennzeichen für den Abgleich normalisieren: Großbuchstaben, nur A–Z/0–9. */
    public static function normalize(?string $plate): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $plate)) ?? '';
    }
}
