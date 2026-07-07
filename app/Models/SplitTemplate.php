<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Aufteilungsvorlage: benannter Satz fester Sachkonten (+ USt-Satz) für
 * wiederkehrende Aufteilungen (z. B. Aral/OIL-Avis). Die Beträge bleiben leer
 * und werden je Umsatz eingetragen.
 *
 * rows: [{ledger_number: string, tax_rate: string, label: string}, …]
 */
class SplitTemplate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'rows' => 'array',
    ];
}
