<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')->label('')->width('1%'),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('parent.name')->label('Übergeordnet')->placeholder('—')->toggleable(),
                TextColumn::make('skr03_account')->label('SKR03')->placeholder('—'),
                TextColumn::make('skr04_account')->label('SKR04')->placeholder('—'),
                TextColumn::make('tax_key')->label('St.-Schlüssel')->placeholder('—')->alignCenter(),
                TextColumn::make('default_tax_rate')->label('Satz')->suffix(' %')->placeholder('—')->alignCenter(),
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
