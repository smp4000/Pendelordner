<?php

namespace Tests\Feature;

use App\Filament\Resources\FintsConnections\Pages\EditFintsConnection;
use App\Models\FintsConnection;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FintsConnectionTestButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_bearbeiten_hat_verbindung_testen_button(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $c = FintsConnection::create([
            'label' => 'VR Bank Fulda',
            'bank_code' => '53060180',
            'fints_url' => 'https://fints2.atruvia.de/cgi-bin/hbciservlet',
            'username' => 'smp4000',
            'pin' => 'geheim',
            'product_id' => '375123E5268D79D5D5F5D0E80',
            'product_version' => '1.0',
            'active' => true,
        ]);

        Livewire::test(EditFintsConnection::class, ['record' => $c->getKey()])
            ->assertOk()
            ->assertActionExists('test');
    }
}
