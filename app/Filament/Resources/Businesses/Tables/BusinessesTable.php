<?php

namespace App\Filament\Resources\Businesses\Tables;

use App\Enums\BusinessType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BusinessesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('short_name')->label('Kurzname')->searchable()->placeholder('—'),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('type')->label('Betriebsart')->badge(),
                TextColumn::make('city')->label('Ort')->searchable()->placeholder('—'),
                TextColumn::make('phone')->label('Telefon')->placeholder('—')->toggleable(),
                TextColumn::make('email')->label('E-Mail')->placeholder('—')->toggleable(),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Betriebsart')->options(BusinessType::class),
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
