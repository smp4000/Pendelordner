<?php

namespace App\Filament\Resources\FintsConnections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FintsConnectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Bezeichnung')->searchable(),
                TextColumn::make('bank_code')->label('BLZ')->searchable(),
                TextColumn::make('username')->label('Benutzerkennung'),
                TextColumn::make('tan_method')->label('TAN-Verfahren')->placeholder('—')->toggleable(),
                TextColumn::make('bank_accounts_count')->label('Konten')->counts('bankAccounts')->badge()->alignCenter(),
                TextColumn::make('last_fetched_at')->label('Letzter Abruf')->dateTime('d.m.Y H:i')->placeholder('—'),
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
