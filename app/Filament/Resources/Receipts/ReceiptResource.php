<?php

namespace App\Filament\Resources\Receipts;

use App\Filament\Resources\Receipts\Pages\CreateReceipt;
use App\Filament\Resources\Receipts\Pages\EditReceipt;
use App\Filament\Resources\Receipts\Pages\ListReceipts;
use App\Filament\Resources\Receipts\Schemas\ReceiptForm;
use App\Filament\Resources\Receipts\Tables\ReceiptsTable;
use App\Models\Receipt;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceiptResource extends Resource
{
    protected static ?string $model = Receipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Belege';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Beleg';

    protected static ?string $pluralModelLabel = 'Belege';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    /** Globale Suche (Modul 11) – inkl. OCR-Volltext. */
    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'receipt_number', 'iban', 'ocr_text'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Lieferant' => $record->supplier?->name,
            'Betrag' => $record->gross_amount ? number_format((float) $record->gross_amount, 2, ',', '.') . ' €' : null,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ReceiptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReceipts::route('/'),
            'create' => CreateReceipt::route('/create'),
            'edit' => EditReceipt::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
