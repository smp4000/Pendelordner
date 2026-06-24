<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('defaultCategory.name')->label('Kategorie')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('defaultCostCenter.name')->label('Kostenstelle')->placeholder('—')->toggleable(),
                TextColumn::make('iban')->label('IBAN')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('creditor_number')->label('Kreditor-Nr.')->placeholder('—')->toggleable(),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('default_category_id')->label('Kategorie')
                    ->relationship('defaultCategory', 'name')->preload(),
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
