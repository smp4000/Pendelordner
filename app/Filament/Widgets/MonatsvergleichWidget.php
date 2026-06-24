<?php

namespace App\Filament\Widgets;

use App\Models\BankTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Monatsvergleich Einnahmen/Ausgaben der letzten 12 Monate (Modul 10). */
class MonatsvergleichWidget extends ChartWidget
{
    protected ?string $heading = 'Monatsvergleich (12 Monate)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $labels = [];
        $income = [];
        $expense = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->startOfMonth()->subMonths($i);
            $next = $month->copy()->addMonth();

            $labels[] = $month->format('m/Y');

            $income[] = round((float) BankTransaction::where('amount', '>', 0)
                ->whereBetween('booking_date', [$month->toDateString(), $next->toDateString()])
                ->sum('amount'), 2);

            $expense[] = round((float) abs(BankTransaction::where('amount', '<', 0)
                ->whereBetween('booking_date', [$month->toDateString(), $next->toDateString()])
                ->sum('amount')), 2);
        }

        return [
            'datasets' => [
                ['label' => 'Einnahmen (€)', 'data' => $income, 'backgroundColor' => '#059669'],
                ['label' => 'Ausgaben (€)', 'data' => $expense, 'backgroundColor' => '#ef4444'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
