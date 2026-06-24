<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FintsZugang extends Model
{
    use SoftDeletes;

    protected $table = 'fints_zugaenge';

    protected $guarded = [];

    protected $casts = [
        'pin' => 'encrypted',
        'aktiv' => 'boolean',
        'letzter_abruf_at' => 'datetime',
    ];

    /**
     * PIN beim Anzeigen in Formularen niemals serialisieren.
     */
    protected $hidden = ['pin'];

    public function bankkonten(): HasMany
    {
        return $this->hasMany(Bankkonto::class);
    }
}
