<?php

use App\Models\Receipt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/admin');
});

/**
 * Streamt eine Belegdatei zur Inline-Vorschau (Modul 6). Geschützt über die
 * Standard-Web-Authentifizierung (Filament-Login).
 */
Route::get('belege/{receipt}/datei', function (Receipt $receipt) {
    abort_unless((bool) $receipt->file_path, 404);

    $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
    abort_unless($disk->exists($receipt->file_path), 404);

    return $disk->response(
        $receipt->file_path,
        $receipt->file_name ?: 'beleg',
        ['Content-Type' => $receipt->mime_type ?: 'application/octet-stream']
    );
})->middleware(['web', 'auth'])->name('beleg.datei');
