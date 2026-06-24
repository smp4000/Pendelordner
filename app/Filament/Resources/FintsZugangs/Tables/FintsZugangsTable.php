<?php

namespace App\Filament\Resources\FintsZugangs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FintsZugangsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bezeichnung')
                    ->searchable(),
                TextColumn::make('bank_code')
                    ->searchable(),
                TextColumn::make('fints_url')
                    ->searchable(),
                TextColumn::make('hbci_version')
                    ->searchable(),
                TextColumn::make('benutzerkennung')
                    ->searchable(),
                TextColumn::make('tan_verfahren')
                    ->searchable(),
                TextColumn::make('tan_medium')
                    ->searchable(),
                TextColumn::make('produkt_id')
                    ->searchable(),
                TextColumn::make('produkt_version')
                    ->searchable(),
                IconColumn::make('aktiv')
                    ->boolean(),
                TextColumn::make('letzter_abruf_at')
                    ->dateTime()
                    ->sortable(),
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
