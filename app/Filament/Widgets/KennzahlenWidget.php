<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionStatus;
use App\Models\BankTransaction;
use App\Models\Receipt;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Kennzahlen-Übersicht für das Dashboard (Modul 10 / Startseite). */
class KennzahlenWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $yearStart = Carbon::now()->startOfYear();

        $newThisMonth = BankTransaction::where('booking_date', '>=', $monthStart)->count();

        $withoutReceipt = BankTransaction::query()->expense()->withoutReceipt()->count();

        $unpaidReceipts = Receipt::query()->unpaid()->count();

        $openAllocations = BankTransaction::whereIn('status', [
            TransactionStatus::Open->value,
            TransactionStatus::PartiallyAllocated->value,
        ])->count();

        $costMonth = (float) BankTransaction::where('amount', '<', 0)
            ->where('booking_date', '>=', $monthStart)->sum('amount');

        $costYear = (float) BankTransaction::where('amount', '<', 0)
            ->where('booking_date', '>=', $yearStart)->sum('amount');

        return [
            Stat::make('Neue Umsätze (Monat)', $newThisMonth)
                ->icon('heroicon-o-banknotes')
                ->color('info'),

            Stat::make('Umsätze ohne Beleg', $withoutReceipt)
                ->icon('heroicon-o-exclamation-triangle')
                ->color($withoutReceipt > 0 ? 'danger' : 'success'),

            Stat::make('Belege ohne Zahlung', $unpaidReceipts)
                ->icon('heroicon-o-document-text')
                ->color($unpaidReceipts > 0 ? 'warning' : 'success'),

            Stat::make('Offene Zuordnungen', $openAllocations)
                ->icon('heroicon-o-clock')
                ->color($openAllocations > 0 ? 'warning' : 'success'),

            Stat::make('Kosten aktueller Monat', $this->money($costMonth))
                ->icon('heroicon-o-calendar')
                ->color('danger'),

            Stat::make('Kosten aktuelles Jahr', $this->money($costYear))
                ->icon('heroicon-o-chart-bar')
                ->color('danger'),
        ];
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' €';
    }
}
