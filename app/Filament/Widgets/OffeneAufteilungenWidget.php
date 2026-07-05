<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Dashboard-Widget: Umsätze, die als geprüft/bezahlt im Bericht sind, deren
 * Aufteilung auf Sachkonten aber noch ergänzt werden muss (Merker
 * "Aufteilung offen"). Zeigt den noch offenen Restbetrag; verschwindet, sobald
 * die Aufteilung vollständig ist oder der Merker entfernt wird.
 */
class OffeneAufteilungenWidget extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return BankTransaction::query()->openSplit()->exists();
    }

    public function getTableHeading(): string
    {
        $count = BankTransaction::query()->openSplit()->count();

        return '✂ Offene Aufteilungen (' . $count . ')';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BankTransaction::query()->openSplit()
                    ->with(['costCenter', 'accountAssignments'])
                    ->orderBy('booking_date')
            )
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('booking_date')
                    ->label('Datum')
                    ->date('d.m.Y'),
                TextColumn::make('counterparty')
                    ->label('Empfänger')
                    ->limit(30)
                    ->placeholder('—'),
                TextColumn::make('costCenter.name')
                    ->label('Kostenstelle')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->alignEnd(),
                TextColumn::make('split_remaining')
                    ->label('Rest offen')
                    ->state(fn (BankTransaction $record): string => number_format($record->split_remaining, 2, ',', '.') . ' €')
                    ->badge()
                    ->color(fn (BankTransaction $record) => abs($record->split_remaining) < 0.005 ? 'success' : 'warning')
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('oeffnen')
                    ->label('Aufteilen')
                    ->icon('heroicon-o-scissors')
                    ->color('primary')
                    ->url(fn (BankTransaction $record): string => Kontoumsatzdetails::getUrl() . '?tx=' . $record->id),
                Action::make('erledigt')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Merker „Aufteilung offen" entfernen?')
                    ->modalDescription('Der Umsatz verschwindet aus dieser Liste. Eine bereits erfasste Aufteilung bleibt erhalten.')
                    ->action(function (BankTransaction $record): void {
                        $record->update(['split_open' => false]);
                        Notification::make()->title('Aus „Offene Aufteilungen" entfernt')->success()->send();
                    }),
            ]);
    }
}
