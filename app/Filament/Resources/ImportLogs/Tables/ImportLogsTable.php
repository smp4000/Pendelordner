<?php

namespace App\Filament\Resources\ImportLogs\Tables;

use App\Enums\ImportSource;
use App\Models\ImportLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('started_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('bankAccount.label')
                    ->label('Konto')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('source')
                    ->label('Quelle')
                    ->badge(),

                TextColumn::make('total_count')
                    ->label('Gesamt')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('new_count')
                    ->label('Neu')
                    ->alignEnd()
                    ->color('success')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('duplicate_count')
                    ->label('Dubletten')
                    ->alignEnd()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('error_count')
                    ->label('Fehler')
                    ->alignEnd()
                    ->color(fn (ImportLog $r): string => $r->error_count > 0 ? 'danger' : 'gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'partial' => 'warning',
                        'error' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('filename')
                    ->label('Datei')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('message')
                    ->label('Meldung')
                    ->limit(60)
                    ->tooltip(fn (ImportLog $r): ?string => $r->message)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Quelle')
                    ->options(ImportSource::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'success' => 'Erfolgreich',
                        'partial' => 'Teilweise',
                        'error' => 'Fehler',
                        'running' => 'Läuft',
                    ]),
            ]);
    }
}
