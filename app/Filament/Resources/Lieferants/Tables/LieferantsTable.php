<?php

namespace App\Filament\Resources\Lieferants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LieferantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('anzeigename')
                    ->searchable(),
                TextColumn::make('standardKategorie.name')
                    ->searchable(),
                TextColumn::make('standardKostenstelle.name')
                    ->searchable(),
                TextColumn::make('standardBetrieb.name')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('bic')
                    ->searchable(),
                TextColumn::make('ust_id')
                    ->searchable(),
                TextColumn::make('steuernummer')
                    ->searchable(),
                TextColumn::make('kreditor_nummer')
                    ->searchable(),
                TextColumn::make('debitor_nummer')
                    ->searchable(),
                TextColumn::make('skr03_konto')
                    ->searchable(),
                TextColumn::make('skr04_konto')
                    ->searchable(),
                TextColumn::make('steuerschluessel')
                    ->searchable(),
                TextColumn::make('strasse')
                    ->searchable(),
                TextColumn::make('plz')
                    ->searchable(),
                TextColumn::make('ort')
                    ->searchable(),
                IconColumn::make('aktiv')
                    ->boolean(),
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
