<?php

namespace Tests\Feature;

use App\Filament\Pages\WaschStammdaten;
use App\Models\Business;
use App\Models\User;
use App\Models\WashArticle;
use App\Models\WashFreePlate;
use App\Models\WashPaymentState;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WaschStammdatenTest extends TestCase
{
    use RefreshDatabase;

    public function test_artikel_kennzeichen_und_states_pflegen(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $petersberg = Business::where('city', 'Petersberg')->firstOrFail();
        $article = WashArticle::where('business_id', $petersberg->id)->where('program', 'Hochglanz')->firstOrFail();

        $comp = Livewire::test(WaschStammdaten::class);

        // EAN und Preis pflegen (deutsches Preisformat).
        $comp->call('updateArticle', $article->id, 'ean', '2091234567890')
            ->call('updateArticle', $article->id, 'price', '17,50');
        $article->refresh();
        $this->assertSame('2091234567890', $article->ean);
        $this->assertSame('17.50', (string) $article->price);

        // Mitarbeiter-Kennzeichen hinzufügen.
        $comp->set('newPlate', 'FD-MA 100')->set('newPlateCategory', 'mitarbeiter')->call('addPlate');
        $plate = WashFreePlate::where('normalized', WashFreePlate::normalize('FD-MA 100'))->firstOrFail();
        $this->assertSame('mitarbeiter', $plate->category);

        // State 9 auf "zählt nicht als Umsatz" stellen.
        $state = WashPaymentState::where('code', 9)->firstOrFail();
        $comp->call('updateState', $state->id, 'counts_as_revenue', false);
        $this->assertFalse($state->fresh()->counts_as_revenue);
    }
}
