<?php

namespace App\Filament\Resources\MatchingRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MatchingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('pattern')->label('Muster')->searchable()->weight('bold'),
                TextColumn::make('pattern_type')->label('Typ')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'counterparty' => 'Empfänger',
                        'purpose' => 'Verwendungszweck',
                        'iban' => 'IBAN',
                        default => 'Beliebig',
                    }),
                TextColumn::make('supplier.name')->label('Lieferant')->placeholder('—'),
                TextColumn::make('category.name')->label('Kategorie')->badge()->color('gray')->placeholder('—'),
                TextColumn::make('costCenter.name')->label('Kostenstelle')->placeholder('—')->toggleable(),
                TextColumn::make('hit_count')->label('Treffer')->badge()->color('success')->alignCenter(),
                TextColumn::make('priority')->label('Priorität')->alignCenter()->sortable(),
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
