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

    public function test_lohnberechnung_ergibt_personalkostenbudget(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create([
            'title' => 'Lohn',
            'year_from' => 2026,
            'year_to' => 2026,
            'payroll_overhead_pct' => 25,
            'vacation_pct' => 10,
        ]);
        (new BusinessPlanTemplate())->apply($plan);

        // 15 Std/Tag × 4 Tage × 52 × 14 €/Std = 43.680 € Lohn p.a.
        $line = $plan->staffLines()->where('label', 'like', 'Kassenschicht Mo%')->first();
        $line->values()->where('year', 2026)->update([
            'hours_per_day' => 15, 'days_per_week' => 4, 'hourly_wage' => 14,
        ]);

        $pay = $plan->fresh()->load('staffLines.values')->payroll();
        $this->assertEqualsWithDelta(43680, $pay[2026]['lohnkosten'], 0.01);
        // + 10 % Urlaub = 4.368; + 25 % auf 48.048 = 12.012; Budget = 60.060.
        $this->assertEqualsWithDelta(4368, $pay[2026]['urlaub'], 0.01);
        $this->assertEqualsWithDelta(60060, $pay[2026]['budget'], 0.01);
    }

    public function test_lohnbudget_fliesst_in_personalkosten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $comp = Livewire::test(Geschaeftsplanung::class)
            ->callAction('createPlan', data: [
                'business_id' => null, 'title' => 'Plan Lohn', 'ts_name' => null,
                'address' => null, 'city' => null, 'year_from' => 2026, 'year_to' => 2026,
            ]);

        $plan = BusinessPlan::firstWhere('title', 'Plan Lohn');
        $line = $plan->staffLines()->where('label', 'like', 'Kassenschicht Mo%')->first();

        $comp->set("staff.{$line->id}.values.2026.hpd", '15')
            ->set("staff.{$line->id}.values.2026.dpw", '4')
            ->set("staff.{$line->id}.values.2026.wage", '14')
            ->call('save');

        // Budget (Standard 25 % / 10 %) landet in der Kostenposition „Personalkosten".
        $pk = $plan->lines()->where('label', 'Personalkosten')->first();
        $val = $pk->values()->where('year', 2026)->first();
        $this->assertEqualsWithDelta(60060, (float) $val->amount, 0.01);
    }

    public function test_pachtberechnung_umsatz_und_festpacht(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create(['title' => 'Pacht', 'year_from' => 2026, 'year_to' => 2027]);
        (new BusinessPlanTemplate())->apply($plan);

        // Umsatzpacht ab Juli 2026, Festpacht 500 €/Monat ab 2027.
        $plan->update([
            'umsatzpacht_start_year' => 2026, 'umsatzpacht_start_month' => 7,
            'festpacht_monthly' => 500, 'festpacht_start_year' => 2027, 'festpacht_start_month' => 1,
        ]);

        $setRev = function (string $label, int $year, float $amount) use ($plan) {
            $plan->lines()->where('label', $label)->first()
                ->values()->where('year', $year)->update(['amount' => $amount]);
        };
        $setRev('Tabakwaren', 2026, 440000);
        $setRev('Tabakwaren', 2027, 440000);
        $setRev('Autowaschanlage', 2026, 43700);
        $setRev('Autowaschanlage', 2027, 48800);

        $plan->leaseBases()->where('source', 'tabak')->update(['rate_pct' => 2.5]);
        $plan->leaseBases()->where('source', 'wasch')->update(['rate_pct' => 6]);
        $plan->leaseBases()->where('source', 'manual')->update(['rate_pct' => 1, 'manual_amount' => 150000]);

        $lease = $plan->fresh()->load(['lines.values', 'leaseBases'])->lease();

        // 2026: (440.000×2,5% + 43.700×6% + 150.000×1%) = 15.122 → ×6/12 = 7.561; Festpacht 0.
        $this->assertEqualsWithDelta(7561, $lease[2026]['total'], 0.01);
        // 2027: (11.000 + 2.928 + 1.500) = 15.428 ganzjährig + 6.000 Festpacht = 21.428.
        $this->assertEqualsWithDelta(15428, $lease[2027]['umsatzpacht'], 0.01);
        $this->assertEqualsWithDelta(6000, $lease[2027]['festpacht'], 0.01);
        $this->assertEqualsWithDelta(21428, $lease[2027]['total'], 0.01);
    }

    public function test_finanzierung_zinsen_aus_kapitalbedarf(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create([
            'title' => 'Fin', 'year_from' => 2026, 'year_to' => 2027,
            'interest_rate' => 10, 'annual_repayment' => 0,
        ]);
        (new BusinessPlanTemplate())->apply($plan);

        $plan->financings()->where('label', 'Warenbestand')->first()->update(['amount' => 30000]);
        $plan = $plan->fresh()->load('financings');

        $this->assertEqualsWithDelta(30000, $plan->capitalNeed(), 0.01);
        $int = $plan->interestByYear();
        $this->assertEqualsWithDelta(3000, $int[2026], 0.01); // 30.000 × 10 %
        $this->assertEqualsWithDelta(3000, $int[2027], 0.01);
    }

    public function test_gewerbesteuer_handels_und_steuerrechtlich(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = BusinessPlan::create([
            'title' => 'GewSt', 'year_from' => 2026, 'year_to' => 2026,
            'gewst_enabled' => true, 'gewst_hebesatz' => 400,
        ]);
        (new BusinessPlanTemplate())->apply($plan);

        // Rohertrag 124.500, keine Kosten -> Gewinn 124.500.
        $plan->lines()->where('label', 'Provision: Vergaserkraftstoffe')->first()
            ->values()->where('year', 2026)->update(['amount' => 124500, 'margin' => 100]);

        $ov = $plan->fresh()->load('lines.values')->overview();
        $this->assertEqualsWithDelta(124500, $ov[2026]['gewinn'], 0.01);
        // Messbetrag (124.500-24.500)×3,5% = 3.500; GewSt ×400% = 14.000.
        $this->assertEqualsWithDelta(14000, $ov[2026]['gewst'], 0.01);
        // anrechenbar 3,8×3.500 = 13.300 -> nicht anrechenbar 700.
        $this->assertEqualsWithDelta(700, $ov[2026]['gewst_na'], 0.01);
        $this->assertEqualsWithDelta(123800, $ov[2026]['gewinn_nach_steuern'], 0.01);
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
