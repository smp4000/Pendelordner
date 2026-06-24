<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Die Startseite leitet auf das Filament-Panel um.
     */
    public function test_die_startseite_leitet_zum_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
