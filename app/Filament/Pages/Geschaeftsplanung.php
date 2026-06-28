<?php

namespace App\Filament\Pages;

use App\Models\BusinessPlan;
use App\Models\BusinessPlanLineValue;
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
     * Geschäftsplanübersicht je Jahr – live aus dem Eingabe-Raster berechnet.
     *
     * @return array<int, array{umsatz: float, rohertrag: float, kosten: float, gewinn: float}>
     */
    public function getOverviewProperty(): array
    {
        $out = [];
        foreach ($this->years as $year) {
            $umsatz = 0.0;
            $rohertrag = 0.0;
            $kosten = 0.0;
            foreach ($this->rows as $row) {
                $amount = $this->num($row['values'][$year]['amount'] ?? 0);
                if ($row['section'] === 'revenue') {
                    $umsatz += $amount;
                    $rohertrag += $amount * $this->num($row['values'][$year]['margin'] ?? 0) / 100;
                } else {
                    $kosten += $amount;
                }
            }
            $out[$year] = [
                'umsatz' => $umsatz,
                'rohertrag' => $rohertrag,
                'kosten' => $kosten,
                'gewinn' => $rohertrag - $kosten,
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
        $perYear = [];
        foreach ($this->years as $year) {
            $personal = 0.0;
            foreach ($this->rows as $row) {
                if ($row['section'] === 'cost' && str_starts_with($row['label'], 'Personalkosten')) {
                    $personal += $this->num($row['values'][$year]['amount'] ?? 0);
                }
            }
            $perYear[$year] = [
                'umsatz' => $overview[$year]['umsatz'],
                'rohertrag' => $overview[$year]['rohertrag'],
                'kosten' => $overview[$year]['kosten'],
                'personal' => $personal,
            ];
        }

        return \App\Services\Plan\LiquidityCalculator::compute($perYear, [
            'vat_rate' => $this->num($this->stamm['vat_rate'] ?? 19),
            'loan_amount' => $this->num($this->stamm['loan_amount'] ?? 0),
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
            'loan_amount' => $this->num($this->stamm['loan_amount'] ?? 0),
            'private_draw' => $this->num($this->stamm['private_draw'] ?? 0),
            'opening_balance' => $this->num($this->stamm['opening_balance'] ?? 0),
            'vat_rate' => $this->num($this->stamm['vat_rate'] ?? 19),
            'annual_repayment' => $this->num($this->stamm['annual_repayment'] ?? 0),
            'notes' => $this->stamm['notes'] ?: null,
        ]);

        $years = range($yearFrom, $yearTo);

        foreach ($this->rows as $row) {
            foreach ($years as $year) {
                $amount = $this->num($row['values'][$year]['amount'] ?? 0);
                $margin = $row['has_margin'] ? $this->num($row['values'][$year]['margin'] ?? 0) : null;

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

        $plan = $this->planId ? BusinessPlan::with('lines.values')->find($this->planId) : null;
        if (! $plan) {
            return;
        }

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
            'notes' => $plan->notes,
        ];

        $years = $plan->years();

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
