<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business.name')
                    ->searchable(),
                TextColumn::make('fintsConnection.id')
                    ->searchable(),
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('bank_name')
                    ->searchable(),
                TextColumn::make('iban')
                    ->searchable(),
                TextColumn::make('bic')
                    ->searchable(),
                TextColumn::make('account_number')
                    ->searchable(),
                TextColumn::make('bank_code')
                    ->searchable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('balance_date')
                    ->dateTime()
                    ->sortable(),
                IconColumn::make('fints_enabled')
                    ->boolean(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('last_fetched_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('color')
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
