<?php

namespace App\Filament\Resources\AccountAssignments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AccountAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('booking_date', 'desc')
            ->columns([
                TextColumn::make('booking_date')->label('Datum')->date('d.m.Y')->sortable(),
                TextColumn::make('booking_text')->label('Buchungstext')->limit(40)->placeholder('—'),
                TextColumn::make('account')->label('Konto')->badge()->color('info'),
                TextColumn::make('contra_account')->label('Gegenkonto')->badge()->color('gray'),
                TextColumn::make('tax_key')->label('BU')->placeholder('—')->alignCenter(),
                TextColumn::make('amount')->label('Betrag')->money('EUR')->alignEnd()->sortable(),
                TextColumn::make('chart_of_accounts')->label('Rahmen')->badge(),
                TextColumn::make('costCenter.name')->label('Kostenstelle')->placeholder('—')->toggleable(),
                IconColumn::make('exported')->label('Exportiert')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('chart_of_accounts')->label('Kontenrahmen')
                    ->options(['skr03' => 'SKR03', 'skr04' => 'SKR04']),
                TernaryFilter::make('exported')->label('Exportiert'),
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
