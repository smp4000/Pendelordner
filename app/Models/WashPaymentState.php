<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Bedeutung eines State-Codes aus dem Zahlungs-Export (pflegbar). */
class WashPaymentState extends Model
{
    protected $guarded = [];

    protected $casts = [
        'code' => 'integer',
        'counts_as_revenue' => 'boolean',
    ];
}
