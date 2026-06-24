<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kostenstelle extends Model
{
    use SoftDeletes;

    protected $table = 'kostenstellen';

    protected $guarded = [];

    protected $casts = [
        'aktiv' => 'boolean',
        'sortierung' => 'integer',
    ];

    public function betrieb(): BelongsTo
    {
        return $this->belongsTo(Betrieb::class);
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
