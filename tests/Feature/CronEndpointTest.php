<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_endpunkt_ist_per_token_geschuetzt(): void
    {
        config(['pendelordner.cron_token' => 'geheim-123']);

        // Ohne/mit falschem Token -> 403
        $this->get('/cron/fetch-mail')->assertForbidden();
        $this->get('/cron/fetch-mail?token=falsch')->assertForbidden();

        // Unbekannte Aufgabe -> 404
        $this->get('/cron/unbekannt?token=geheim-123')->assertNotFound();

        // Mit korrektem Token -> 200 (Mail-Eingang ist im Test deaktiviert)
        $this->get('/cron/fetch-mail?token=geheim-123')->assertOk();
    }

    public function test_cron_endpunkt_ohne_konfiguriertes_token_gesperrt(): void
    {
        config(['pendelordner.cron_token' => null]);
        $this->get('/cron/fetch-mail?token=')->assertForbidden();
    }
}
