<?php

namespace App\Filament\Resources\Bankkontos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BankkontosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('betrieb.name')
                    ->searchable(),
                TextColumn::make('fintsZugang.id')
                    ->searchable(),
                TextColumn::make('bezeichnung')
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('bic')
                    ->searchable(),
                TextColumn::make('kontonummer')
                    ->searchable(),
                TextColumn::make('blz')
                    ->searchable(),
                TextColumn::make('waehrung')
                    ->searchable(),
                TextColumn::make('saldo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('saldo_datum')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('fints_aktiv')
                    ->boolean(),
                IconColumn::make('aktiv')
                    ->boolean(),
                TextColumn::make('letzter_abruf_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('farbe')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
