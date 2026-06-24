<?php

namespace App\Filament\Resources\MatchingRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MatchingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pattern')
                    ->searchable(),
                TextColumn::make('pattern_type')
                    ->searchable(),
                TextColumn::make('supplier.name')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('costCenter.name')
                    ->searchable(),
                TextColumn::make('business.name')
                    ->searchable(),
                TextColumn::make('skr03_account')
                    ->searchable(),
                TextColumn::make('skr04_account')
                    ->searchable(),
                TextColumn::make('tax_key')
                    ->searchable(),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hit_count')
                    ->numeric()
                    ->sortable(),
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
