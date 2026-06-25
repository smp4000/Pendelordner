<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Geplanter automatischer Bankabruf (Modul 1)
|--------------------------------------------------------------------------
| Ruft täglich (Standard 06:00 Uhr) die Umsätze aller FinTS-fähigen Konten ab.
| Uhrzeit/Frequenz über .env steuerbar (BANK_FETCH_TIME). Voraussetzung: der
| Laravel-Scheduler muss laufen (siehe docs/Installation.md – unter Windows per
| Aufgabenplanung "php artisan schedule:run" minütlich, oder dauerhaft per
| "php artisan schedule:work").
*/
Schedule::command('bank:fetch')
    ->dailyAt(env('BANK_FETCH_TIME', '06:00'))
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
