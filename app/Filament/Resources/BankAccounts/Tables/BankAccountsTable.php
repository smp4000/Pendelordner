<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Bezeichnung')->searchable(),
                TextColumn::make('bank_name')->label('Bank')->placeholder('—')->toggleable(),
                TextColumn::make('iban')->label('IBAN')->searchable()->placeholder('—'),
                TextColumn::make('business.name')->label('Betrieb')->badge()->placeholder('—'),
                TextColumn::make('balance')->label('Saldo')->money('EUR')->alignEnd()->placeholder('—'),
                IconColumn::make('fints_enabled')->label('FinTS')->boolean()->alignCenter(),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('fints_enabled')->label('FinTS aktiv'),
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
