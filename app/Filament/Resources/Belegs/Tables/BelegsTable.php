<?php

namespace App\Filament\Resources\Belegs\Tables;

use App\Enums\BelegStatus;
use App\Enums\BelegTyp;
use App\Enums\OcrStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class BelegsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('rechnungsdatum', 'desc')
            ->columns([
                TextColumn::make('typ')
                    ->label('Art')
                    ->badge(),
                TextColumn::make('rechnungsnummer')
                    ->label('Rechnungs-Nr.')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('lieferant.name')
                    ->label('Lieferant')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('rechnungsdatum')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('betrag_brutto')
                    ->label('Brutto')
                    ->money('EUR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('kategorie.name')
                    ->label('Kategorie')
                    ->badge()->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('bankumsaetze_count')
                    ->label('Zuordnung')
                    ->counts('bankumsaetze')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->alignCenter(),
                TextColumn::make('ocr_status')
                    ->label('OCR')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                IconColumn::make('bezahlt')
                    ->label('Bezahlt')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('typ')
                    ->label('Belegart')
                    ->options(BelegTyp::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(BelegStatus::class),
                SelectFilter::make('ocr_status')
                    ->label('OCR-Status')
                    ->options(OcrStatus::class),
                SelectFilter::make('lieferant_id')
                    ->label('Lieferant')
                    ->relationship('lieferant', 'name')
                    ->searchable()->preload(),
                SelectFilter::make('betrieb_id')
                    ->label('Betrieb')
                    ->relationship('betrieb', 'name')
                    ->preload(),
                TernaryFilter::make('bezahlt')
                    ->label('Bezahlt'),
                TernaryFilter::make('nicht_zugeordnet')
                    ->label('Zuordnung')
                    ->placeholder('Alle')
                    ->trueLabel('Ohne Umsatz')
                    ->falseLabel('Mit Umsatz')
                    ->queries(
                        true: fn ($q) => $q->whereDoesntHave('bankumsaetze'),
                        false: fn ($q) => $q->whereHas('bankumsaetze'),
                    ),
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
