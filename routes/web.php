<?php

use App\Models\Receipt;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return redirect('/admin');
});

/**
 * Cron-Endpunkte für URL-basierte Cronjobs (z. B. all-inkl). Per Token
 * abgesichert: /cron/fetch-mail?token=… ruft den Mail-Eingang ab,
 * /cron/fetch-bank den FinTS-Abruf, /cron/schedule den Laravel-Scheduler.
 */
Route::get('cron/{task}', function (string $task) {
    $token = (string) config('pendelordner.cron_token');
    abort_unless($token !== '' && hash_equals($token, (string) request('token')), 403, 'Ungültiges Token.');

    $allowed = [
        'fetch-mail' => 'belege:fetch-mail',
        'fetch-bank' => 'bank:fetch',
        'schedule' => 'schedule:run',
    ];
    abort_unless(isset($allowed[$task]), 404);

    Artisan::call($allowed[$task]);

    return response(Artisan::output() ?: 'OK', 200)->header('Content-Type', 'text/plain');
})->name('cron.run');

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
