<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankTransaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/**
 * Dashboard-Widget: offene Hinweise, auf die noch reagiert werden muss
 * (z. B. "Gutschrift angefordert"). Bleiben sichtbar, bis sie über "Erledigt"
 * bestätigt werden. Speist sich aus den Bankumsätzen mit gesetztem
 * accountant_note und note_open = true.
 */
class OffeneHinweiseWidget extends TableWidget
{
    protected static ?int $sort = -2;

    protected int|string|array $columnSpan = 'full';

    /** Nur einblenden, wenn es tatsächlich offene Hinweise gibt. */
    public static function canView(): bool
    {
        return BankTransaction::query()->openNote()->exists();
    }

    public function getTableHeading(): string
    {
        $count = BankTransaction::query()->openNote()->count();

        return '⚠ Offene Hinweise (' . $count . ')';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BankTransaction::query()->openNote()
                    ->with(['bankAccount', 'costCenter'])
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
                TextColumn::make('accountant_note')
                    ->label('Hinweis')
                    ->wrap()
                    ->weight('bold')
                    ->color('warning'),
                TextColumn::make('costCenter.name')
                    ->label('Kostenstelle')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('oeffnen')
                    ->label('Öffnen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (BankTransaction $record): string => Kontoumsatzdetails::getUrl() . '?tx=' . $record->id),
                Action::make('erledigt')
                    ->label('Erledigt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Hinweis als erledigt markieren?')
                    ->modalDescription('Der Hinweistext bleibt am Umsatz erhalten, verschwindet aber aus dieser Liste.')
                    ->action(function (BankTransaction $record): void {
                        $record->update(['note_open' => false]);
                        \App\Support\OffeneHinweisGlocke::clear($record->id);
                        Notification::make()->title('Hinweis erledigt')->success()->send();
                    }),
            ]);
    }
}
