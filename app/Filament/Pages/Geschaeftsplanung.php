<?php

namespace App\Filament\Pages;

use App\Models\BusinessPlan;
use App\Models\BusinessPlanFinancing;
use App\Models\BusinessPlanLeaseBase;
use App\Models\BusinessPlanLineValue;
use App\Models\BusinessPlanStaffValue;
use App\Models\Business;
use App\Services\Plan\BusinessPlanTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Geschäftsplanung (neues Modul): mehrjähriger Geschäftsplan je Tankstelle,
 * Aufbau wie die Aral/GP-OIL-Vorlage. Eingabe von Umsatz (mit BVD-Marge) und
 * Kosten je Planjahr; die Geschäftsplanübersicht (Umsatz, Rohertrag, Kosten,
 * Gewinn) wird live berechnet.
 */
class Geschaeftsplanung extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.geschaeftsplanung';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Planung';

    protected static ?string $title = 'Geschäftsplanung';

    protected static ?string $navigationLabel = 'Geschäftsplanung';

    public ?int $planId = null;

    /** @var array<string, mixed> Stammdaten des Plans */
    public array $stamm = [];

    /**
     * Plan-Positionen, je Zeile mit Werten pro Jahr.
     *
     * @var array<int, array{id:int, section:string, category:string, label:string, has_margin:bool, values: array<int, array{amount:string, margin:string}>}>
     */
    public array $rows = [];

    /**
     * Lohnzeilen der Personalkostenberechnung, je Zeile mit Werten pro Jahr.
     *
     * @var array<int, array{id:int, category:string, label:string, is_deduction:bool, values: array<int, array{hpd:string, dpw:string, wage:string}>}>
     */
    public array $staff = [];

    /**
     * Bemessungsgrundlagen der Shopumsatzpacht.
     *
     * @var array<int, array{id:int, label:string, source:string, rate:string, manual:string}>
     */
    public array $leaseBases = [];

    /**
     * Kapitalbedarf-Positionen der Finanzierung.
     *
     * @var array<int, array{id:int, label:string, finance_type:string, amount:string}>
     */
    public array $financings = [];

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->planId = BusinessPlan::orderBy('title')->value('id');
        $this->loadPlan();
    }

    /** Auswahlliste aller Pläne. */
    public function getPlanOptionsProperty(): array
    {
        return BusinessPlan::orderBy('title')->pluck('title', 'id')->all();
    }

    public function updatedPlanId(): void
    {
        $this->loadPlan();
    }

    /** Planjahre als Liste (für die Spalten). */
    public function getYearsProperty(): array
    {
        $from = (int) ($this->stamm['year_from'] ?? 0);
        $to = (int) ($this->stamm['year_to'] ?? 0);
        if ($from < 2000 || $to < $from) {
            return [];
        }

        return range($from, $to);
    }

    /**
     * Personalkostenberechnung (Lohn) je Jahr – live aus dem Lohn-Raster.
     *
     * @return array<int, array{hours: float, lohnkosten: float, urlaub: float, nebenkosten: float, budget: float}>
     */
    public function getPayrollProperty(): array
    {
        $rowsByYear = [];
        foreach ($this->years as $idx => $year) {
            $rows = [];
            foreach ($this->staff as $s) {
                $rows[] = [
                    'hours_per_day' => $this->num($s['values'][$year]['hpd'] ?? 0),
                    'days_per_week' => $this->num($s['values'][$year]['dpw'] ?? 0),
                    'hourly_wage' => $this->effectiveWage($s, $idx),
                    'is_deduction' => (bool) $s['is_deduction'],
                ];
            }
            $rowsByYear[$year] = $rows;
        }

        return \App\Services\Plan\PayrollCalculator::compute($rowsByYear, [
            'vacation_pct' => $this->num($this->stamm['vacation_pct'] ?? 10),
            'fest_pct' => $this->num($this->stamm['staff_fest_pct'] ?? 0),
            'ag_fest' => $this->num($this->stamm['ag_pct_fest'] ?? 22.5),
            'ag_aushilfe' => $this->num($this->stamm['ag_pct_aushilfe'] ?? 31.15),
            'sonntag_hours' => $this->num($this->stamm['sonntag_hours'] ?? 0),
            'sonntag_pct' => $this->num($this->stamm['sonntag_pct'] ?? 0),
            'feiertag_hours' => $this->num($this->stamm['feiertag_hours'] ?? 0),
            'feiertag_pct' => $this->num($this->stamm['feiertag_pct'] ?? 0),
            'nacht_hours' => $this->num($this->stamm['nacht_hours'] ?? 0),
            'nacht_pct' => $this->num($this->stamm['nacht_pct'] ?? 25),
        ]);
    }

    /** Effektiver Stundenlohn einer Lohnzeile im Jahr (mit Lohnentwicklung). */
    public function effectiveWage(array $s, int $yearIndex): float
    {
        $years = $this->years;
        $growth = $this->num($this->stamm['wage_growth_pct'] ?? 0) / 100;
        if ($growth > 0) {
            $base = $this->num($s['values'][$years[0]]['wage'] ?? 0);

            return $base * (1 + $growth) ** $yearIndex;
        }

        return $this->num($s['values'][$years[$yearIndex]]['wage'] ?? 0);
    }

    /** Lohn p.a. einer einzelnen Lohnzeile in einem Jahr (für die Anzeige). */
    public function staffWage(array $s, int $year): float
    {
        $idx = (int) array_search($year, $this->years, true);

        return $this->num($s['values'][$year]['hpd'] ?? 0)
            * $this->num($s['values'][$year]['dpw'] ?? 0) * 52
            * $this->effectiveWage($s, $idx);
    }

    /** Umsatz einer Umsatzzeile (live aus dem Raster) per Bezeichnung. */
    private function revAmountLive(string $label, int $year): float
    {
        foreach ($this->rows as $row) {
            if ($row['section'] === 'revenue' && $row['label'] === $label) {
                return $this->num($row['values'][$year]['amount'] ?? 0);
            }
        }

        return 0.0;
    }

    /** Summe einer Umsatzgruppe (live) per category. */
    private function groupAmountLive(string $category, int $year): float
    {
        $sum = 0.0;
        foreach ($this->rows as $row) {
            if ($row['section'] === 'revenue' && $row['category'] === $category) {
                $sum += $this->num($row['values'][$year]['amount'] ?? 0);
            }
        }

        return $sum;
    }

    /** Bemessungs-Umsatz einer Pacht-Grundlage je Jahr (öffentlich, für die Anzeige). */
    public function leaseAmount(array $base, int $year): float
    {
        return $this->leaseBaseAmountLive($base, $year);
    }

    /** Bemessungs-Umsatz einer Pacht-Grundlage je Jahr (live). */
    private function leaseBaseAmountLive(array $base, int $year): float
    {
        return match ($base['source']) {
            'tabak' => $this->revAmountLive('Tabakwaren', $year),
            'wasch' => $this->revAmountLive('Autowaschanlage', $year),
            'shop_rest' => max(0.0, $this->groupAmountLive('Shop / Bistro', $year)
                - $this->revAmountLive('Tabakwaren', $year)
                - $this->revAmountLive('Karten, Bücher, Zeitschriften', $year)),
            default => $this->num($base['manual'] ?? 0),
        };
    }

    /**
     * Pachtberechnung je Jahr (Shopumsatzpacht + Festpacht) – live.
     *
     * @return array<int, array{umsatzpacht: float, festpacht: float, total: float}>
     */
    public function getLeaseProperty(): array
    {
        $basesByYear = [];
        foreach ($this->years as $year) {
            $rows = [];
            foreach ($this->leaseBases as $b) {
                $rows[] = ['amount' => $this->leaseBaseAmountLive($b, $year), 'rate' => $this->num($b['rate'] ?? 0)];
            }
            $basesByYear[$year] = $rows;
        }

        $upSY = ($this->stamm['umsatzpacht_start_year'] ?? '') !== '' ? (int) $this->stamm['umsatzpacht_start_year'] : null;
        $fpSY = ($this->stamm['festpacht_start_year'] ?? '') !== '' ? (int) $this->stamm['festpacht_start_year'] : null;

        return \App\Services\Plan\LeaseCalculator::compute($basesByYear, [
            'up_start_year' => $upSY,
            'up_start_month' => (int) ($this->stamm['umsatzpacht_start_month'] ?? 1),
            'fest_monthly' => $this->num($this->stamm['festpacht_monthly'] ?? 0),
            'fest_start_year' => $fpSY,
            'fest_start_month' => (int) ($this->stamm['festpacht_start_month'] ?? 1),
        ]);
    }

    /** Kapitalbedarf = Summe der Finanzierungspositionen (live). */
    public function getCapitalNeedProperty(): float
    {
        $sum = 0.0;
        foreach ($this->financings as $f) {
            $sum += $this->num($f['amount'] ?? 0);
        }

        return $sum;
    }

    /**
     * Jährliche Zinsen auf das Darlehen (live).
     *
     * @return array<int, float>
     */
    public function getInterestProperty(): array
    {
        return \App\Services\Plan\FinanceCalculator::interestByYear(
            $this->capitalNeed,
            $this->num($this->stamm['annual_repayment'] ?? 0),
            $this->num($this->stamm['interest_rate'] ?? 0),
            $this->years,
        );
    }

    /**
     * Geschäftsplanübersicht je Jahr – live aus dem Eingabe-Raster berechnet.
     *
     * @return array<int, array{umsatz: float, rohertrag: float, kosten: float, gewinn: float}>
     */
    public function getOverviewProperty(): array
    {
        $payroll = $this->payroll;
        $lease = $this->lease;
        $interest = $this->interest;
        $hebesatz = $this->num($this->stamm['gewst_hebesatz'] ?? 0);
        $gewstOn = ! empty($this->stamm['gewst_enabled']);
        $out = [];
        foreach ($this->years as $year) {
            $umsatz = 0.0;
            $rohertrag = 0.0;
            $kosten = 0.0;
            foreach ($this->rows as $row) {
                if ($row['section'] === 'revenue') {
                    $amount = $this->num($row['values'][$year]['amount'] ?? 0);
                    $umsatz += $amount;
                    $rohertrag += $amount * $this->num($row['values'][$year]['margin'] ?? 0) / 100;
                } else {
                    // Personalkosten/Pacht/Zinsen aus den jeweiligen Berechnungen.
                    $amount = match ($row['label']) {
                        'Personalkosten' => $payroll[$year]['budget'] ?? 0,
                        'Pacht - Station' => $lease[$year]['total'] ?? 0,
                        'Zinsen- und Geldkosten' => $interest[$year] ?? 0,
                        default => $this->num($row['values'][$year]['amount'] ?? 0),
                    };
                    $kosten += $amount;
                }
            }
            $gewinn = $rohertrag - $kosten;
            $tax = \App\Services\Plan\TaxCalculator::gewst($gewinn, $hebesatz, $gewstOn);
            $out[$year] = [
                'umsatz' => $umsatz,
                'rohertrag' => $rohertrag,
                'kosten' => $kosten,
                'gewinn' => $gewinn,
                'gewst' => $tax['gewst'],
                'gewst_na' => $tax['nicht_anrechenbar'],
                'gewinn_nach_steuern' => $gewinn - $tax['nicht_anrechenbar'],
            ];
        }

        return $out;
    }

    /**
     * Liquiditätsplanung – live aus dem Eingabe-Raster + Plan-Annahmen.
     *
     * @return array<int, array{months: array<int, array<string, float>>, totals: array<string, float>, end: float, credit: float}>
     */
    public function getLiquidityProperty(): array
    {
        $overview = $this->overview;
        $payroll = $this->payroll;
        $perYear = [];
        foreach ($this->years as $year) {
            $personal = 0.0;
            foreach ($this->rows as $row) {
                if ($row['section'] === 'cost' && str_starts_with($row['label'], 'Personalkosten')) {
                    $personal += $row['label'] === 'Personalkosten'
                        ? ($payroll[$year]['budget'] ?? 0)
                        : $this->num($row['values'][$year]['amount'] ?? 0);
                }
            }
            $perYear[$year] = [
                'umsatz' => $overview[$year]['umsatz'],
                'rohertrag' => $overview[$year]['rohertrag'],
                'kosten' => $overview[$year]['kosten'],
                'personal' => $personal,
                'gewst' => $overview[$year]['gewst'] ?? 0,
            ];
        }

        return \App\Services\Plan\LiquidityCalculator::compute($perYear, [
            'vat_rate' => $this->num($this->stamm['vat_rate'] ?? 19),
            'loan_amount' => $this->capitalNeed,
            'annual_repayment' => $this->num($this->stamm['annual_repayment'] ?? 0),
            'private_draw' => $this->num($this->stamm['private_draw'] ?? 0),
            'opening_balance' => $this->num($this->stamm['opening_balance'] ?? 0),
        ]);
    }

    /** Rohertrag einer einzelnen Umsatzzeile in einem Jahr (für die Anzeige). */
    public function rowRohertrag(array $row, int $year): float
    {
        $amount = $this->num($row['values'][$year]['amount'] ?? 0);

        return $amount * $this->num($row['values'][$year]['margin'] ?? 0) / 100;
    }

    /** Modal: neuen Geschäftsplan anlegen (mit Standard-Positionen). */
    public function createPlanAction(): Action
    {
        return Action::make('createPlan')
            ->label('Neuer Plan')
            ->icon('heroicon-o-plus')
            ->modalHeading('Neuen Geschäftsplan anlegen')
            ->schema([
                Select::make('business_id')
                    ->label('Tankstelle / Betrieb')
                    ->options(Business::orderBy('sort_order')->get()->pluck('display_label', 'id'))
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        $b = Business::find($state);
                        if ($b) {
                            $set('title', $b->name);
                            $set('ts_name', $b->name);
                            $set('city', trim(($b->postal_code ? $b->postal_code . ' ' : '') . ($b->city ?? '')));
                        }
                    }),
                TextInput::make('title')->label('Titel des Plans')->required(),
                TextInput::make('ts_name')->label('TS-Name'),
                TextInput::make('address')->label('Adresse'),
                TextInput::make('city')->label('PLZ / Ort'),
                TextInput::make('year_from')->label('Erstes Planjahr')->numeric()->default(now()->year)->required(),
                TextInput::make('year_to')->label('Letztes Planjahr')->numeric()->default(now()->year + 2)->required(),
            ])
            ->action(function (array $data): void {
                $plan = BusinessPlan::create([
                    'business_id' => $data['business_id'] ?: null,
                    'title' => $data['title'],
                    'ts_name' => $data['ts_name'] ?: null,
                    'address' => $data['address'] ?: null,
                    'city' => $data['city'] ?: null,
                    'year_from' => (int) $data['year_from'],
                    'year_to' => max((int) $data['year_from'], (int) $data['year_to']),
                ]);

                (new BusinessPlanTemplate())->apply($plan);

                $this->planId = $plan->id;
                $this->loadPlan();

                Notification::make()->title('Geschäftsplan angelegt')->success()->send();
            });
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label('Plan speichern')
            ->icon('heroicon-o-check')
            ->action(fn () => $this->save());
    }

    public function deletePlanAction(): Action
    {
        return Action::make('deletePlan')
            ->label('Plan löschen')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn () => (bool) $this->planId)
            ->action(function (): void {
                BusinessPlan::find($this->planId)?->delete();
                $this->planId = BusinessPlan::orderBy('title')->value('id');
                $this->loadPlan();
                Notification::make()->title('Plan gelöscht')->success()->send();
            });
    }

    public function save(): void
    {
        $plan = BusinessPlan::find($this->planId);
        if (! $plan) {
            Notification::make()->title('Kein Plan gewählt')->warning()->send();

            return;
        }

        $yearFrom = (int) ($this->stamm['year_from'] ?? $plan->year_from);
        $yearTo = max($yearFrom, (int) ($this->stamm['year_to'] ?? $plan->year_to));

        $plan->update([
            'title' => trim((string) ($this->stamm['title'] ?? $plan->title)) ?: $plan->title,
            'ts_name' => $this->stamm['ts_name'] ?: null,
            'address' => $this->stamm['address'] ?: null,
            'city' => $this->stamm['city'] ?: null,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'loan_amount' => $this->capitalNeed,
            'private_draw' => $this->num($this->stamm['private_draw'] ?? 0),
            'opening_balance' => $this->num($this->stamm['opening_balance'] ?? 0),
            'vat_rate' => $this->num($this->stamm['vat_rate'] ?? 19),
            'annual_repayment' => $this->num($this->stamm['annual_repayment'] ?? 0),
            'vacation_pct' => $this->num($this->stamm['vacation_pct'] ?? 10),
            'staff_fest_pct' => $this->num($this->stamm['staff_fest_pct'] ?? 0),
            'ag_pct_fest' => $this->num($this->stamm['ag_pct_fest'] ?? 22.5),
            'ag_pct_aushilfe' => $this->num($this->stamm['ag_pct_aushilfe'] ?? 31.15),
            'sonntag_hours' => $this->num($this->stamm['sonntag_hours'] ?? 0),
            'sonntag_pct' => $this->num($this->stamm['sonntag_pct'] ?? 0),
            'feiertag_hours' => $this->num($this->stamm['feiertag_hours'] ?? 0),
            'feiertag_pct' => $this->num($this->stamm['feiertag_pct'] ?? 0),
            'nacht_hours' => $this->num($this->stamm['nacht_hours'] ?? 0),
            'nacht_pct' => $this->num($this->stamm['nacht_pct'] ?? 25),
            'wage_growth_pct' => $this->num($this->stamm['wage_growth_pct'] ?? 0),
            'umsatzpacht_start_year' => ($this->stamm['umsatzpacht_start_year'] ?? '') !== '' ? (int) $this->stamm['umsatzpacht_start_year'] : null,
            'umsatzpacht_start_month' => (int) ($this->stamm['umsatzpacht_start_month'] ?? 1) ?: 1,
            'festpacht_monthly' => $this->num($this->stamm['festpacht_monthly'] ?? 0),
            'festpacht_start_year' => ($this->stamm['festpacht_start_year'] ?? '') !== '' ? (int) $this->stamm['festpacht_start_year'] : null,
            'festpacht_start_month' => (int) ($this->stamm['festpacht_start_month'] ?? 1) ?: 1,
            'interest_rate' => $this->num($this->stamm['interest_rate'] ?? 0),
            'gewst_enabled' => ! empty($this->stamm['gewst_enabled']),
            'gewst_hebesatz' => $this->num($this->stamm['gewst_hebesatz'] ?? 0),
            'notes' => $this->stamm['notes'] ?: null,
        ]);

        // Bemessungsgrundlagen der Umsatzpacht (Satz + manueller Umsatz) speichern.
        foreach ($this->leaseBases as $b) {
            BusinessPlanLeaseBase::where('id', $b['id'])->update([
                'rate_pct' => $this->num($b['rate'] ?? 0),
                'manual_amount' => $this->num($b['manual'] ?? 0),
            ]);
        }

        // Kapitalbedarf-Positionen speichern.
        foreach ($this->financings as $f) {
            BusinessPlanFinancing::where('id', $f['id'])->update([
                'amount' => $this->num($f['amount'] ?? 0),
                'finance_type' => trim((string) ($f['finance_type'] ?? '')) ?: null,
            ]);
        }

        $years = range($yearFrom, $yearTo);
        $payroll = $this->payroll;   // Personalkostenbudget je Jahr aus der Lohnberechnung
        $lease = $this->lease;       // Stationspacht je Jahr aus der Pachtberechnung
        $interest = $this->interest; // Zinsen je Jahr aus der Finanzierung

        // Lohnzeilen speichern.
        foreach ($this->staff as $s) {
            foreach ($years as $year) {
                BusinessPlanStaffValue::updateOrCreate(
                    ['business_plan_staff_line_id' => $s['id'], 'year' => $year],
                    [
                        'hours_per_day' => $this->num($s['values'][$year]['hpd'] ?? 0),
                        'days_per_week' => $this->num($s['values'][$year]['dpw'] ?? 0),
                        'hourly_wage' => $this->num($s['values'][$year]['wage'] ?? 0),
                    ],
                );
            }
            BusinessPlanStaffValue::where('business_plan_staff_line_id', $s['id'])
                ->whereNotIn('year', $years)->delete();
        }

        foreach ($this->rows as $row) {
            foreach ($years as $year) {
                // Personalkosten aus Lohnberechnung, Pacht aus Pachtberechnung (nicht manuell).
                if ($row['section'] === 'cost' && $row['label'] === 'Personalkosten') {
                    $amount = $payroll[$year]['budget'] ?? 0;
                    $margin = null;
                } elseif ($row['section'] === 'cost' && $row['label'] === 'Pacht - Station') {
                    $amount = $lease[$year]['total'] ?? 0;
                    $margin = null;
                } elseif ($row['section'] === 'cost' && $row['label'] === 'Zinsen- und Geldkosten') {
                    $amount = $interest[$year] ?? 0;
                    $margin = null;
                } else {
                    $amount = $this->num($row['values'][$year]['amount'] ?? 0);
                    $margin = $row['has_margin'] ? $this->num($row['values'][$year]['margin'] ?? 0) : null;
                }

                BusinessPlanLineValue::updateOrCreate(
                    ['business_plan_line_id' => $row['id'], 'year' => $year],
                    ['amount' => $amount, 'margin' => $margin],
                );
            }
            // Werte außerhalb des Planzeitraums entfernen.
            BusinessPlanLineValue::where('business_plan_line_id', $row['id'])
                ->whereNotIn('year', $years)
                ->delete();
        }

        $this->loadPlan();

        Notification::make()->title('Geschäftsplan gespeichert')->success()->send();
    }

    /** Plan + Positionen in das Eingabe-Raster laden. */
    private function loadPlan(): void
    {
        $this->stamm = [];
        $this->rows = [];
        $this->staff = [];
        $this->leaseBases = [];
        $this->financings = [];

        $plan = $this->planId ? BusinessPlan::find($this->planId) : null;
        if (! $plan) {
            return;
        }

        // Bestehende Pläne um fehlende Lohnbereiche (Werkstatt/Gastro) ergänzen.
        (new \App\Services\Plan\BusinessPlanTemplate())->ensureStaffAreas($plan);
        $plan->load(['lines.values', 'staffLines.values', 'leaseBases', 'financings']);

        $this->stamm = [
            'title' => $plan->title,
            'ts_name' => $plan->ts_name,
            'address' => $plan->address,
            'city' => $plan->city,
            'year_from' => $plan->year_from,
            'year_to' => $plan->year_to,
            'loan_amount' => $this->fmt($plan->loan_amount),
            'private_draw' => $this->fmt($plan->private_draw),
            'opening_balance' => $this->fmt($plan->opening_balance),
            'vat_rate' => $this->fmt($plan->vat_rate),
            'annual_repayment' => $this->fmt($plan->annual_repayment),
            'vacation_pct' => $this->fmt($plan->vacation_pct),
            'staff_fest_pct' => $this->fmt($plan->staff_fest_pct),
            'ag_pct_fest' => $this->fmt($plan->ag_pct_fest),
            'ag_pct_aushilfe' => $this->fmt($plan->ag_pct_aushilfe),
            'sonntag_hours' => $this->fmt($plan->sonntag_hours),
            'sonntag_pct' => $this->fmt($plan->sonntag_pct),
            'feiertag_hours' => $this->fmt($plan->feiertag_hours),
            'feiertag_pct' => $this->fmt($plan->feiertag_pct),
            'nacht_hours' => $this->fmt($plan->nacht_hours),
            'nacht_pct' => $this->fmt($plan->nacht_pct),
            'wage_growth_pct' => $this->fmt($plan->wage_growth_pct),
            'umsatzpacht_start_year' => $plan->umsatzpacht_start_year ? (string) $plan->umsatzpacht_start_year : '',
            'umsatzpacht_start_month' => (string) ($plan->umsatzpacht_start_month ?: 1),
            'festpacht_monthly' => $this->fmt($plan->festpacht_monthly),
            'festpacht_start_year' => $plan->festpacht_start_year ? (string) $plan->festpacht_start_year : '',
            'festpacht_start_month' => (string) ($plan->festpacht_start_month ?: 1),
            'interest_rate' => $this->fmt($plan->interest_rate),
            'gewst_enabled' => (bool) $plan->gewst_enabled,
            'gewst_hebesatz' => $this->fmt($plan->gewst_hebesatz),
            'notes' => $plan->notes,
        ];

        foreach ($plan->financings as $f) {
            $this->financings[$f->id] = [
                'id' => $f->id,
                'label' => $f->label,
                'finance_type' => (string) $f->finance_type,
                'amount' => $this->fmt($f->amount),
            ];
        }

        foreach ($plan->leaseBases as $base) {
            $this->leaseBases[$base->id] = [
                'id' => $base->id,
                'label' => $base->label,
                'source' => $base->source,
                'rate' => $this->fmt($base->rate_pct),
                'manual' => $this->fmt($base->manual_amount),
            ];
        }

        $years = $plan->years();

        foreach ($plan->staffLines as $line) {
            $values = [];
            foreach ($years as $year) {
                $v = $line->values->firstWhere('year', $year);
                $values[$year] = [
                    'hpd' => $this->fmt($v->hours_per_day ?? 0),
                    'dpw' => $this->fmt($v->days_per_week ?? 0),
                    'wage' => $this->fmt($v->hourly_wage ?? 0),
                ];
            }
            $this->staff[$line->id] = [
                'id' => $line->id,
                'area' => (string) ($line->area ?: 'shop'),
                'category' => (string) $line->category,
                'label' => $line->label,
                'is_deduction' => (bool) $line->is_deduction,
                'values' => $values,
            ];
        }

        foreach ($plan->lines as $line) {
            $values = [];
            foreach ($years as $year) {
                $v = $line->values->firstWhere('year', $year);
                $values[$year] = [
                    'amount' => $this->fmt($v->amount ?? 0),
                    'margin' => $line->has_margin ? $this->fmt($v->margin ?? 0) : '',
                ];
            }
            $this->rows[$line->id] = [
                'id' => $line->id,
                'section' => $line->section,
                'category' => (string) $line->category,
                'label' => $line->label,
                'has_margin' => (bool) $line->has_margin,
                'values' => $values,
            ];
        }
    }

    /** Deutsche Zahleneingabe ("440.000,00" / "13,6") in float wandeln. */
    private function num(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        $s = trim((string) $value);
        if ($s === '') {
            return 0.0;
        }
        // Punkt = Tausender, Komma = Dezimal.
        $s = str_replace(['.', ' '], '', $s);
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }

    /** float für die Anzeige im Eingabefeld deutsch formatieren. */
    private function fmt(mixed $value): string
    {
        $f = (float) $value;

        return $f == 0.0 ? '' : number_format($f, 2, ',', '.');
    }
}
