<?php

namespace App\Filament\Resources\Suppliers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('display_name')
                    ->searchable(),
                TextColumn::make('defaultCategory.name')
                    ->searchable(),
                TextColumn::make('defaultCostCenter.name')
                    ->searchable(),
                TextColumn::make('defaultBusiness.name')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('bic')
                    ->searchable(),
                TextColumn::make('vat_id')
                    ->searchable(),
                TextColumn::make('tax_number')
                    ->searchable(),
                TextColumn::make('creditor_number')
                    ->searchable(),
                TextColumn::make('debtor_number')
                    ->searchable(),
                TextColumn::make('skr03_account')
                    ->searchable(),
                TextColumn::make('skr04_account')
                    ->searchable(),
                TextColumn::make('tax_key')
                    ->searchable(),
                TextColumn::make('street')
                    ->searchable(),
                TextColumn::make('postal_code')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                IconColumn::make('active')
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
