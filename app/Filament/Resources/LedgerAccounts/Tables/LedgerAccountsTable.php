<?php

namespace App\Filament\Resources\LedgerAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgerAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('chart')->label('Kontenrahmen')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'edtas' => 'edtas',
                        'kfz' => 'Kfz-Handel',
                        'gastro' => 'Gastronomie',
                        default => $state,
                    }),
                TextColumn::make('number')->label('Konto')->searchable()->sortable()->weight('bold'),
                TextColumn::make('name')->label('Bezeichnung')->searchable()->wrap(),
                TextColumn::make('group')->label('Zuordnung (GA)')->badge()->color('gray')->placeholder('—')->toggleable(),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('chart')->label('Kontenrahmen')->options([
                    'edtas' => 'edtas',
                    'kfz' => 'Kfz-Handel',
                    'gastro' => 'Gastronomie',
                ]),
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
