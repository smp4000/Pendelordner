<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Einzelzeile (Betrag | Text) einer Steuerbüro-Hinweiskarte (Modul 12). */
class ReportNoteLine extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(ReportNote::class, 'report_note_id');
    }
}
