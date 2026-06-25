<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bank-Vorlage für FinTS-Zugänge (Modul 1). */
class BankPreset extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
