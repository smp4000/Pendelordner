<?php

namespace App\Filament\Widgets;

use App\Models\BankTransaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Kosten je Kategorie im laufenden Jahr (Modul 10). */
class KostenJeKategorieWidget extends ChartWidget
{
    protected ?string $heading = 'Kosten je Kategorie (laufendes Jahr)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        // Lädt alle Jahresumsätze und aggregiert in PHP -> 5 Minuten cachen.
        return Cache::remember('widget.kosten-je-kategorie', 300, fn () => $this->computeData());
    }

    private function computeData(): array
    {
        // Aufgeteilte Umsätze (Kategorie-Split) fließen positionsweise je
        // Kategorie ein; nicht aufgeteilte Ausgaben zählen wie bisher mit ihrer
        // Kategorie. So bildet die Auswertung die G&V-Aufteilung korrekt ab.
        $transactions = BankTransaction::query()
            ->with(['category', 'accountAssignments.category'])
            ->where('booking_date', '>=', Carbon::now()->startOfYear())
            ->get();

        $totals = [];
        foreach ($transactions as $t) {
            $splits = $t->accountAssignments->whereNotNull('category_id');

            if ($splits->isNotEmpty()) {
                foreach ($splits as $a) {
                    $name = $a->category?->name ?? 'Ohne Kategorie';
                    $totals[$name] = ($totals[$name] ?? 0) + abs((float) $a->amount);
                }
            } elseif ((float) $t->amount < 0 && $t->category) {
                $totals[$t->category->name] = ($totals[$t->category->name] ?? 0) + abs((float) $t->amount);
            }
        }

        arsort($totals);
        $totals = array_slice($totals, 0, 12, true);

        return [
            'datasets' => [[
                'label' => 'Betrag (€)',
                'data' => array_map(fn ($v) => round((float) $v, 2), array_values($totals)),
                'backgroundColor' => [
                    '#059669', '#0ea5e9', '#8b5cf6', '#ec4899', '#f59e0b', '#ef4444',
                    '#14b8a6', '#6366f1', '#d946ef', '#f97316', '#84cc16', '#64748b',
                ],
            ]],
            'labels' => array_keys($totals),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
