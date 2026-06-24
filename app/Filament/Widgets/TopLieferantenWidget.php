<?php

namespace App\Filament\Widgets;

use App\Models\Supplier;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Top-Lieferanten nach Ausgaben im laufenden Jahr (Modul 10). */
class TopLieferantenWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Top-Lieferanten (laufendes Jahr)';
    }

    public function table(Table $table): Table
    {
        $yearStart = Carbon::now()->startOfYear()->toDateString();

        return $table
            ->query(
                Supplier::query()
                    ->select('suppliers.*')
                    ->selectRaw(
                        '(SELECT COALESCE(SUM(ABS(amount)), 0) FROM bank_transactions '
                        . 'WHERE bank_transactions.supplier_id = suppliers.id '
                        . 'AND bank_transactions.amount < 0 '
                        . 'AND bank_transactions.booking_date >= ?) as total_expense',
                        [$yearStart]
                    )
                    ->having('total_expense', '>', 0)
                    ->orderByDesc('total_expense')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')->label('Lieferant'),
                TextColumn::make('defaultCategory.name')->label('Kategorie')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('total_expense')
                    ->label('Ausgaben (Jahr)')
                    ->money('EUR')
                    ->alignEnd(),
            ]);
    }
}
