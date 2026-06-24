<?php

namespace App\Filament\Resources\Bankumsatzs\Tables;

use App\Enums\BankumsatzStatus;
use App\Models\Bankumsatz;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Tabellenansicht der Bankumsätze im Stil von Lexware Office (Modul 2):
 * Buchungsdatum, Empfänger, Verwendungszweck, Betrag, Kategorie, Kostenstelle,
 * zugeordnete Belege, Differenz und Status mit Ampelfarben (Rot/Gelb/Grün).
 */
class BankumsatzsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['belege', 'kategorie', 'kostenstelle', 'bankkonto', 'betrieb']))
            ->defaultSort('buchungsdatum', 'desc')
            ->columns([
                TextColumn::make('buchungsdatum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('empfaenger')
                    ->label('Empfänger / Auftraggeber')
                    ->description(fn (Bankumsatz $r): ?string => $r->verwendungszweck
                        ? Str::limit($r->verwendungszweck, 60)
                        : null)
                    ->searchable(['empfaenger', 'verwendungszweck'])
                    ->wrap(),

                TextColumn::make('betrag')
                    ->label('Betrag')
                    ->money('EUR')
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn (Bankumsatz $r): string => $r->betrag < 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('kategorie.name')
                    ->label('Kategorie')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('kostenstelle.name')
                    ->label('Kostenstelle')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('belege_anzahl')
                    ->label('Belege')
                    ->badge()
                    ->alignCenter()
                    ->state(fn (Bankumsatz $r): int => $r->belege->count())
                    ->color(fn (Bankumsatz $r): string => $r->belege->isEmpty() ? 'danger' : 'success'),

                TextColumn::make('differenz')
                    ->label('Differenz')
                    ->alignEnd()
                    ->state(fn (Bankumsatz $r): string => number_format($r->differenz, 2, ',', '.') . ' €')
                    ->color(fn (Bankumsatz $r): string => abs($r->differenz) < 0.01 ? 'success' : 'warning')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('geprueft')
                    ->label('Geprüft')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('bankkonto.bezeichnung')
                    ->label('Bankkonto')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('betrieb.kurzname')
                    ->label('Betrieb')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notiz')
                    ->label('Notiz')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Ampel: linker Farbbalken je Status (Rot=offen, Gelb=teilweise, Grün=fertig)
            ->recordClasses(fn (Bankumsatz $r): string => match ($r->status) {
                BankumsatzStatus::Offen => 'border-l-4 border-l-danger-500',
                BankumsatzStatus::TeilweiseZugeordnet => 'border-l-4 border-l-warning-500',
                default => 'border-l-4 border-l-success-500',
            })
            ->filters([
                SelectFilter::make('bankkonto_id')
                    ->label('Bankkonto')
                    ->relationship('bankkonto', 'bezeichnung')
                    ->preload(),

                SelectFilter::make('betrieb_id')
                    ->label('Betrieb')
                    ->relationship('betrieb', 'name')
                    ->preload(),

                SelectFilter::make('kategorie_id')
                    ->label('Kategorie')
                    ->relationship('kategorie', 'name')
                    ->preload(),

                SelectFilter::make('kostenstelle_id')
                    ->label('Kostenstelle')
                    ->relationship('kostenstelle', 'name')
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BankumsatzStatus::class),

                TernaryFilter::make('ohne_beleg')
                    ->label('Beleglage')
                    ->placeholder('Alle')
                    ->trueLabel('Ohne Beleg')
                    ->falseLabel('Mit Beleg')
                    ->queries(
                        true: fn (Builder $q) => $q->whereDoesntHave('belege'),
                        false: fn (Builder $q) => $q->whereHas('belege'),
                    ),

                TernaryFilter::make('geprueft')
                    ->label('Geprüft'),

                Filter::make('zeitraum')
                    ->schema([
                        DatePicker::make('von')->label('Von'),
                        DatePicker::make('bis')->label('Bis'),
                    ])
                    ->query(fn (Builder $q, array $data): Builder => $q
                        ->when($data['von'] ?? null, fn (Builder $q, $d) => $q->whereDate('buchungsdatum', '>=', $d))
                        ->when($data['bis'] ?? null, fn (Builder $q, $d) => $q->whereDate('buchungsdatum', '<=', $d))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
