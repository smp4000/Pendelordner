<?php

namespace App\Filament\Resources\Receipts\Tables;

use App\Enums\OcrStatus;
use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use App\Models\Receipt;
use App\Services\Ocr\OcrService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('type')->label('Art')->badge(),
                TextColumn::make('invoice_number')->label('Rechnungs-Nr.')->searchable()->placeholder('—'),
                TextColumn::make('supplier.name')->label('Lieferant')->searchable()->placeholder('—'),
                TextColumn::make('invoice_date')->label('Datum')->date('d.m.Y')->sortable(),
                TextColumn::make('gross_amount')->label('Brutto')->money('EUR')->alignEnd()->sortable(),
                TextColumn::make('category.name')->label('Kategorie')->badge()->color('gray')->placeholder('—')->toggleable(),
                TextColumn::make('bank_transactions_count')
                    ->label('Zuordnung')
                    ->counts('bankTransactions')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->alignCenter(),
                TextColumn::make('ocr_status')->label('OCR')->badge()->toggleable(),
                TextColumn::make('status')->label('Status')->badge(),
                IconColumn::make('paid')->label('Bezahlt')->boolean()->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Belegart')->options(ReceiptType::class),
                SelectFilter::make('status')->label('Status')->options(ReceiptStatus::class),
                SelectFilter::make('ocr_status')->label('OCR-Status')->options(OcrStatus::class),
                SelectFilter::make('supplier_id')->label('Lieferant')
                    ->relationship('supplier', 'name')->searchable()->preload(),
                SelectFilter::make('business_id')->label('Betrieb')
                    ->relationship('business', 'name')->preload(),
                TernaryFilter::make('paid')->label('Bezahlt'),
                TernaryFilter::make('unallocated')
                    ->label('Zuordnung')
                    ->placeholder('Alle')
                    ->trueLabel('Ohne Umsatz')
                    ->falseLabel('Mit Umsatz')
                    ->queries(
                        true: fn ($q) => $q->whereDoesntHave('bankTransactions'),
                        false: fn ($q) => $q->whereHas('bankTransactions'),
                    ),
            ])
            ->recordActions([
                Action::make('ocr')
                    ->label('OCR')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('gray')
                    ->visible(fn (Receipt $record): bool => filled($record->file_path))
                    ->action(function (Receipt $record): void {
                        (new OcrService())->process($record);
                        Notification::make()
                            ->title('OCR ausgeführt')
                            ->body('Status: ' . $record->ocr_status->getLabel())
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
