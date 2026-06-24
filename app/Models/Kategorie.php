<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kategorie extends Model
{
    use SoftDeletes;

    protected $table = 'kategorien';

    protected $guarded = [];

    protected $casts = [
        'aktiv' => 'boolean',
        'sortierung' => 'integer',
        'standard_steuersatz' => 'decimal:2',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Kategorie::class, 'parent_id');
    }

    public function kinder(): HasMany
    {
        return $this->hasMany(Kategorie::class, 'parent_id');
    }

    public function bankumsaetze(): HasMany
    {
        return $this->hasMany(Bankumsatz::class);
    }

    public function belege(): HasMany
    {
        return $this->hasMany(Beleg::class);
    }
}
