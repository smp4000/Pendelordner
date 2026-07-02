<?php

namespace App\Filament\Resources\BankTransactions;

use App\Filament\Resources\BankTransactions\Pages\CreateBankTransaction;
use App\Filament\Resources\BankTransactions\Pages\EditBankTransaction;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Filament\Resources\BankTransactions\RelationManagers\ReceiptsRelationManager;
use App\Filament\Resources\BankTransactions\Schemas\BankTransactionForm;
use App\Filament\Resources\BankTransactions\Tables\BankTransactionsTable;
use App\Models\BankTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Bankumsatz';

    protected static ?string $pluralModelLabel = 'Bankumsätze';

    protected static ?string $recordTitleAttribute = 'counterparty';

    /** Globale Suche (Modul 11). */
    public static function getGloballySearchableAttributes(): array
    {
        return ['counterparty', 'purpose', 'bank_reference', 'counterparty_iban'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'Betrag' => number_format((float) $record->amount, 2, ',', '.') . ' €',
            'Datum' => $record->booking_date?->format('d.m.Y'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return BankTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ReceiptsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankTransactions::route('/'),
            'create' => CreateBankTransaction::route('/create'),
            'edit' => EditBankTransaction::route('/{record}/edit'),
        ];
    }

    /** Anzahl der noch nicht geprüften Umsätze als Navigations-Badge (Rot). */
    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::query()->where('reviewed', false)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Ungeprüfte Umsätze';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
