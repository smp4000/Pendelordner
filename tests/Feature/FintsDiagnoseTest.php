<?php

namespace Tests\Feature;

use App\Filament\Pages\FintsKonten;
use App\Models\FintsConnection;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FintsDiagnoseTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnose_zeigt_produktbezeichnung(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $c = FintsConnection::create([
            'label' => 'VR Bank Fulda',
            'bank_code' => '53060180',
            'fints_url' => 'https://fints2.atruvia.de/cgi-bin/hbciservlet',
            'username' => 'NETKEY123',
            'pin' => 'geheim',
            'product_id' => '375123E5268D79D5D5F5D0E80',
            'product_version' => '1.0',
            'active' => true,
        ]);

        Livewire::test(FintsKonten::class)
            ->set('connectionId', $c->id)
            ->call('diagnose')
            ->assertNotified();
    }
}
