<?php

namespace App\Filament\Resources\ZuordnungsRegels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZuordnungsRegelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('muster')
                    ->searchable(),
                TextColumn::make('muster_typ')
                    ->searchable(),
                TextColumn::make('lieferant.name')
                    ->searchable(),
                TextColumn::make('kategorie.name')
                    ->searchable(),
                TextColumn::make('kostenstelle.name')
                    ->searchable(),
                TextColumn::make('betrieb.name')
                    ->searchable(),
                TextColumn::make('skr03_konto')
                    ->searchable(),
                TextColumn::make('skr04_konto')
                    ->searchable(),
                TextColumn::make('steuerschluessel')
                    ->searchable(),
                TextColumn::make('prioritaet')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('treffer_anzahl')
                    ->numeric()
                    ->sortable(),
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
            ])
            ->filters([
                //
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
