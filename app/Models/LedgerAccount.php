<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sachkonto eines Kontenrahmens (Modul 13). */
class LedgerAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function getLabelAttribute(): string
    {
        return $this->number . ' – ' . $this->name;
    }
}
