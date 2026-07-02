<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\LedgerAccount;
use App\Models\PosSale;
use App\Services\Pos\PosReportImporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Kassenumsätze (Ist) importieren und je Tankstelle/Monat auswerten.
 * Import der Aral-Kassenabrechnung (CSV); Kraftstoff-Provision je Liter wird
 * anhand der Einstellung der Tankstelle berechnet.
 */
class Kassenumsaetze extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.kassenumsaetze';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Kassenumsätze';

    protected static ?string $navigationLabel = 'Kassenumsätze';

    /** @var array<int, mixed> */
    public array $posFiles = [];

    public ?int $businessId = null;

    public string $year = '';

    public string $month = '';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->businessId = Business::orderBy('sort_order')->value('id');
        $this->year = (string) Carbon::now()->year;
        $this->month = (string) Carbon::now()->month;
    }

    /** @return Collection<int, Business> */
    public function getBusinessesProperty(): Collection
    {
        return Business::orderBy('sort_order')->get();
    }

    /** Kassenabrechnung(en) einlesen. */
    public function importFiles(): void
    {
        $this->validate([
            'posFiles' => 'required|array',
            'posFiles.*' => 'file|max:20480',
        ], [], ['posFiles' => 'Dateien']);

        $importer = new PosReportImporter();
        $ok = 0;
        $unmatched = [];

        foreach ($this->posFiles as $file) {
            try {
                $res = $importer->import((string) $file->get());
                if ($res['business'] && $res['count'] > 0) {
                    $ok++;
                    // Ansicht auf den zuletzt importierten Monat stellen.
                    $this->businessId = $res['business']->id;
                    $this->year = (string) $res['period']->year;
                    $this->month = (string) $res['period']->month;
                } else {
                    $unmatched[] = $res['station'] ?: $file->getClientOriginalName();
                }
            } catch (Throwable $e) {
                report($e);
                $unmatched[] = $file->getClientOriginalName();
            }
        }

        $this->reset('posFiles');

        if ($ok > 0) {
            Notification::make()->title($ok . ' Kassenabrechnung(en) importiert')->success()->send();
        }
        if ($unmatched) {
            Notification::make()
                ->title('Nicht zugeordnet')
                ->body('Keine Tankstelle zur Stationsnummer gefunden: ' . implode(', ', $unmatched)
                    . '. Bitte die Stationsnummer beim Betrieb hinterlegen.')
                ->warning()->persistent()->send();
        }
    }

    /** Gewählten Monat als erster Tag. */
    private function selectedMonth(): ?Carbon
    {
        $y = (int) $this->year;
        $m = (int) $this->month;

        return ($y >= 2000 && $m >= 1 && $m <= 12) ? Carbon::create($y, $m, 1)->startOfMonth() : null;
    }

    /** @return Collection<int, PosSale> */
    public function getSalesProperty(): Collection
    {
        $month = $this->selectedMonth();
        if (! $this->businessId || ! $month) {
            return collect();
        }

        return PosSale::where('business_id', $this->businessId)
            ->whereYear('period', $month->year)
            ->whereMonth('period', $month->month)
            ->orderBy('fn')
            ->get();
    }

    /**
     * Kennzahlen: Kraftstoff-Liter/Provision, sonstige Erlöse (brutto), Erlös gesamt.
     *
     * @return array<string, float>
     */
    public function getSummaryProperty(): array
    {
        $sales = $this->sales;
        $business = $this->businessId ? Business::find($this->businessId) : null;
        $ct = (float) ($business->fuel_commission_ct ?? 0);

        $fuelLiters = (float) $sales->where('is_fuel', true)->sum('quantity');
        $fuelGross = (float) $sales->where('is_fuel', true)->sum('amount_gross');
        $provision = $fuelLiters * $ct / 100;
        $otherGross = (float) $sales->where('is_fuel', false)->sum('amount_gross');

        return [
            'fuel_liters' => $fuelLiters,
            'fuel_gross' => $fuelGross,
            'commission_ct' => $ct,
            'provision' => round($provision, 2),
            'other_gross' => round($otherGross, 2),
            'total' => round($provision + $otherGross, 2),
        ];
    }

    /**
     * Verbuchung der Erlöse je eDTAS-Konto (Netto/USt aus brutto). Kraftstoff
     * wird als Provision auf das Provisionskonto der Tankstelle gebucht.
     *
     * @return array{rows: list<array{account: string, name: ?string, net: float, ust: float, brutto: float}>, sum: array{net: float, ust: float, brutto: float}, fuel_account: ?string}
     */
    public function getBookingProperty(): array
    {
        $sales = $this->sales;
        $business = $this->businessId ? Business::find($this->businessId) : null;
        $ct = (float) ($business->fuel_commission_ct ?? 0);
        $fuelAcct = $business?->fuel_provision_account ?: null;

        $rows = [];
        $add = function (string $acct, float $net, float $ust, float $brutto) use (&$rows): void {
            $rows[$acct] ??= ['account' => $acct, 'name' => null, 'net' => 0.0, 'ust' => 0.0, 'brutto' => 0.0];
            $rows[$acct]['net'] += $net;
            $rows[$acct]['ust'] += $ust;
            $rows[$acct]['brutto'] += $brutto;
        };

        foreach ($sales as $s) {
            if ($s->is_fuel) {
                $net = (float) $s->quantity * $ct / 100;
                $ust = $net * 0.19;
                $add($fuelAcct ?: '—', $net, $ust, $net + $ust);
            } else {
                $brutto = (float) $s->amount_gross;
                $rate = $s->tax_rate;
                $net = $rate > 0 ? $brutto / (1 + $rate / 100) : $brutto;
                $add((string) ($s->ekw_konto ?: '—'), $net, $brutto - $net, $brutto);
            }
        }

        $names = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
            ->whereIn('number', array_keys($rows))->pluck('name', 'number');
        foreach ($rows as $acct => &$r) {
            $r['name'] = $names[$acct] ?? null;
            $r['net'] = round($r['net'], 2);
            $r['ust'] = round($r['ust'], 2);
            $r['brutto'] = round($r['brutto'], 2);
        }
        unset($r);
        ksort($rows);

        return [
            'rows' => array_values($rows),
            'sum' => [
                'net' => round(array_sum(array_column($rows, 'net')), 2),
                'ust' => round(array_sum(array_column($rows, 'ust')), 2),
                'brutto' => round(array_sum(array_column($rows, 'brutto')), 2),
            ],
            'fuel_account' => $fuelAcct,
        ];
    }
}
