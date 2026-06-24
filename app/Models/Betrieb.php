<?php

namespace App\Models;

use App\Enums\BetriebTyp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Betrieb extends Model
{
    use SoftDeletes;

    protected $table = 'betriebe';

    protected $guarded = [];

    protected $casts = [
        'typ' => BetriebTyp::class,
        'aktiv' => 'boolean',
        'sortierung' => 'integer',
    ];

    public function bankkonten(): HasMany
    {
        return $this->hasMany(Bankkonto::class);
    }

    public function kostenstellen(): HasMany
    {
        return $this->hasMany(Kostenstelle::class);
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
