<?php

namespace App\Filament\Widgets;

use App\Models\BankTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Kosten je Kategorie im laufenden Jahr (Modul 10). */
class KostenJeKategorieWidget extends ChartWidget
{
    protected ?string $heading = 'Kosten je Kategorie (laufendes Jahr)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $rows = BankTransaction::query()
            ->selectRaw('categories.name as name, SUM(ABS(bank_transactions.amount)) as total')
            ->join('categories', 'categories.id', '=', 'bank_transactions.category_id')
            ->where('bank_transactions.amount', '<', 0)
            ->where('bank_transactions.booking_date', '>=', Carbon::now()->startOfYear())
            ->groupBy('categories.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Kosten (€)',
                'data' => $rows->pluck('total')->map(fn ($v) => round((float) $v, 2)),
                'backgroundColor' => [
                    '#059669', '#0ea5e9', '#8b5cf6', '#ec4899', '#f59e0b', '#ef4444',
                    '#14b8a6', '#6366f1', '#d946ef', '#f97316', '#84cc16', '#64748b',
                ],
            ]],
            'labels' => $rows->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
