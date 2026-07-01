<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Vordefinierter Hinweis-Text für Steuerbüro-Dokumente (erweiterbar). */
class SteuerNoteText extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
