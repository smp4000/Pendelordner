<?php

namespace App\Filament\Resources\CostCenters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CostCentersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')->label('')->width('1%'),
                TextColumn::make('number')->label('Nummer')->placeholder('—'),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('business.name')->label('Betrieb')->badge()->placeholder('—'),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Aktiv'),
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
