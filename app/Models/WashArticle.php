<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Waschprogramm als Kassen-Artikel je Betrieb (EAN, VK-Preis, Konto).
 * Bildet den App-Programmnamen auf den Kassen-Artikel ab.
 */
class WashArticle extends Model
{
    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
