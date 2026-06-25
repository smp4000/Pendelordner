<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Auswertungen (Modul 10): Kostenaufschlüsselung je Betrieb, Kostenstelle,
 * Kategorie, Bankkonto und Lieferant für einen wählbaren Zeitraum
 * (Monat/Quartal/Jahr/Vorjahr).
 */
class Auswertungen extends Page
{
    protected string $view = 'filament.pages.auswertungen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|UnitEnum|null $navigationGroup = 'Auswertungen';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Auswertungen';

    protected static ?string $navigationLabel = 'Auswertungen';

    public string $period = 'this_month';

    /** @return array<string, string> */
    public function getPeriodOptionsProperty(): array
    {
        return [
            'this_month' => 'Aktueller Monat',
            'this_quarter' => 'Aktuelles Quartal',
            'this_year' => 'Aktuelles Jahr',
            'last_year' => 'Vorjahr',
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    private function baseQuery()
    {
        [$from, $to] = $this->range();

        return BankTransaction::query()
            ->where('amount', '<', 0)
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()]);
    }

    /** Aggregiert die Ausgaben (absolut) nach einer Relation. */
    private function breakdown(string $joinTable, string $foreignKey, string $labelColumn): Collection
    {
        return $this->baseQuery()
            ->selectRaw("COALESCE({$joinTable}.{$labelColumn}, 'Ohne Zuordnung') as label, SUM(ABS(bank_transactions.amount)) as total, COUNT(*) as anzahl")
            ->leftJoin($joinTable, "{$joinTable}.id", '=', "bank_transactions.{$foreignKey}")
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();
    }

    public function getByBusinessProperty(): Collection
    {
        return $this->breakdown('businesses', 'business_id', 'name');
    }

    public function getByCostCenterProperty(): Collection
    {
        return $this->breakdown('cost_centers', 'cost_center_id', 'name');
    }

    public function getByCategoryProperty(): Collection
    {
        return $this->breakdown('categories', 'category_id', 'name');
    }

    public function getByBankAccountProperty(): Collection
    {
        return $this->breakdown('bank_accounts', 'bank_account_id', 'label');
    }

    public function getBySupplierProperty(): Collection
    {
        return $this->breakdown('suppliers', 'supplier_id', 'name')->take(10);
    }

    public function getTotalProperty(): float
    {
        return (float) abs($this->baseQuery()->sum('amount'));
    }
}
