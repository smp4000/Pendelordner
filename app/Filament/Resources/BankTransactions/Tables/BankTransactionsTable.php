<?php

namespace App\Filament\Resources\BankTransactions\Tables;

use App\Enums\TransactionStatus;
use App\Models\BankTransaction;
use App\Services\Accounting\KontierungService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Bankumsätze im Stil von Lexware Office (Modul 2): Buchungsdatum, Empfänger,
 * Verwendungszweck, Betrag, Kategorie, Kostenstelle, zugeordnete Belege,
 * Differenz und Status mit Ampelfarben (Rot/Gelb/Grün).
 */
class BankTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['receipts', 'category', 'costCenter', 'bankAccount', 'business']))
            ->defaultSort('booking_date', 'desc')
            ->columns([
                TextColumn::make('booking_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('counterparty')
                    ->label('Empfänger / Auftraggeber')
                    ->description(fn (BankTransaction $r): ?string => $r->purpose
                        ? Str::limit($r->purpose, 60)
                        : null)
                    ->searchable(['counterparty', 'purpose'])
                    ->wrap(),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (BankTransaction $r): string => $r->amount < 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategorie')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('costCenter.name')
                    ->label('Kostenstelle')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('receipts_count')
                    ->label('Belege')
                    ->badge()
                    ->alignCenter()
                    ->state(fn (BankTransaction $r): int => $r->receipts->count())
                    ->color(fn (BankTransaction $r): string => $r->receipts->isEmpty() ? 'danger' : 'success'),

                TextColumn::make('difference')
                    ->label('Differenz')
                    ->alignEnd()
                    ->state(fn (BankTransaction $r): string => number_format($r->difference, 2, ',', '.') . ' €')
                    ->color(fn (BankTransaction $r): string => abs($r->difference) < 0.01 ? 'success' : 'warning')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('reviewed')
                    ->label('Geprüft')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('bankAccount.label')
                    ->label('Bankkonto')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('business.short_name')
                    ->label('Betrieb')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('note')
                    ->label('Notiz')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Ampel: linker Farbbalken je Status (Rot=offen, Gelb=teilweise, Grün=fertig)
            ->recordClasses(fn (BankTransaction $r): string => match ($r->status) {
                TransactionStatus::Open => 'border-l-4 border-l-danger-500',
                TransactionStatus::PartiallyAllocated => 'border-l-4 border-l-warning-500',
                default => 'border-l-4 border-l-success-500',
            })
            ->filters([
                SelectFilter::make('bank_account_id')
                    ->label('Bankkonto')
                    ->relationship('bankAccount', 'label')
                    ->preload(),

                SelectFilter::make('business_id')
                    ->label('Betrieb')
                    ->relationship('business', 'name')
                    ->preload(),

                SelectFilter::make('category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->preload(),

                SelectFilter::make('cost_center_id')
                    ->label('Kostenstelle')
                    ->relationship('costCenter', 'name')
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TransactionStatus::class),

                TernaryFilter::make('without_receipt')
                    ->label('Beleglage')
                    ->placeholder('Alle')
                    ->trueLabel('Ohne Beleg')
                    ->falseLabel('Mit Beleg')
                    ->queries(
                        true: fn (Builder $q) => $q->whereDoesntHave('receipts'),
                        false: fn (Builder $q) => $q->whereHas('receipts'),
                    ),

                TernaryFilter::make('reviewed')
                    ->label('Geprüft'),

                Filter::make('period')
                    ->schema([
                        DatePicker::make('from')->label('Von'),
                        DatePicker::make('until')->label('Bis'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('booking_date', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('booking_date', '<=', $d))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('kontieren')
                        ->label('Kontieren')
                        ->icon('heroicon-o-calculator')
                        ->color('primary')
                        ->action(function (Collection $records): void {
                            $result = (new KontierungService())->bookMany($records);
                            Notification::make()
                                ->title('Kontierung erstellt')
                                ->body("{$result['booked']} kontiert, {$result['skipped']} übersprungen (kein Konto).")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
