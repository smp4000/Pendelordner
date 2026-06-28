<?php

namespace Tests\Feature;

use App\Filament\Pages\Geschaeftsplanung;
use App\Models\BusinessPlan;
use App\Models\User;
use App\Services\Plan\BusinessPlanTemplate;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_vorlage_und_uebersicht_rechnen_korrekt(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create(['title' => 'Test', 'year_from' => 2026, 'year_to' => 2028]);
        (new BusinessPlanTemplate())->apply($plan);

        // Standard-Positionen wurden angelegt (Umsatz + Kosten).
        $this->assertGreaterThan(20, $plan->lines()->count());

        $tab = $plan->lines()->where('label', 'Tabakwaren')->first();
        $tab->values()->where('year', 2026)->update(['amount' => 440000, 'margin' => 13.6]);
        $pk = $plan->lines()->where('label', 'Personalkosten')->first();
        $pk->values()->where('year', 2026)->update(['amount' => 110940]);

        $ov = $plan->fresh()->load('lines.values')->overview();
        $this->assertEqualsWithDelta(440000, $ov[2026]['umsatz'], 0.01);
        $this->assertEqualsWithDelta(59840, $ov[2026]['rohertrag'], 0.01); // 440.000 * 13,6 %
        $this->assertEqualsWithDelta(110940, $ov[2026]['kosten'], 0.01);
        $this->assertEqualsWithDelta(59840 - 110940, $ov[2026]['gewinn'], 0.01);
    }

    public function test_liquiditaet_rechnet_monatlich_und_kumuliert(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create([
            'title' => 'Liqui',
            'year_from' => 2026,
            'year_to' => 2026,
            'vat_rate' => 19,
            'opening_balance' => 1000,
        ]);
        (new BusinessPlanTemplate())->apply($plan);

        $plan->lines()->where('label', 'Tabakwaren')->first()
            ->values()->where('year', 2026)->update(['amount' => 120000, 'margin' => 25]);
        $plan->lines()->where('label', 'Personalkosten')->first()
            ->values()->where('year', 2026)->update(['amount' => 24000]);

        $liq = $plan->fresh()->load('lines.values')->liquidity();

        // Umsatz 120.000 / Rohertrag 30.000 / Wareneinsatz 90.000, USt 19 %.
        // Monatssaldo = 11.900 (Einn.) - 8.925 (Ware) - 2.000 (Personal) - 475 (USt) = 500.
        $this->assertEqualsWithDelta(500, $liq[2026]['months'][1]['saldo'], 0.01);
        $this->assertEqualsWithDelta(6000, $liq[2026]['totals']['saldo'], 0.01);
        $this->assertEqualsWithDelta(7000, $liq[2026]['end'], 0.01); // 1.000 Anfang + 6.000
    }

    public function test_seite_legt_plan_an_und_speichert_werte(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $comp = Livewire::test(Geschaeftsplanung::class)
            ->callAction('createPlan', data: [
                'business_id' => null,
                'title' => 'Plan A',
                'ts_name' => null,
                'address' => null,
                'city' => null,
                'year_from' => 2026,
                'year_to' => 2027,
            ]);

        $plan = BusinessPlan::firstWhere('title', 'Plan A');
        $this->assertNotNull($plan);
        $this->assertNotEmpty($plan->lines);

        // Deutsche Zahleneingabe setzen und speichern.
        $line = $plan->lines()->where('label', 'Getränke')->first();
        $comp->set("rows.{$line->id}.values.2026.amount", '71.500,00')
            ->set("rows.{$line->id}.values.2026.margin", '52')
            ->call('save');

        $val = $line->values()->where('year', 2026)->first();
        $this->assertEqualsWithDelta(71500, (float) $val->amount, 0.01);
        $this->assertEqualsWithDelta(52, (float) $val->margin, 0.01);
    }
}
