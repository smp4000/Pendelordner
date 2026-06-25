<?php

namespace App\Filament\Resources\LedgerAccounts;

use App\Filament\Resources\LedgerAccounts\Pages\CreateLedgerAccount;
use App\Filament\Resources\LedgerAccounts\Pages\EditLedgerAccount;
use App\Filament\Resources\LedgerAccounts\Pages\ListLedgerAccounts;
use App\Filament\Resources\LedgerAccounts\Schemas\LedgerAccountForm;
use App\Filament\Resources\LedgerAccounts\Tables\LedgerAccountsTable;
use App\Models\LedgerAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LedgerAccountResource extends Resource
{
    protected static ?string $model = LedgerAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Buchhaltung';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Sachkonto';

    protected static ?string $pluralModelLabel = 'Sachkonten (Kontenrahmen)';

    public static function form(Schema $schema): Schema
    {
        return LedgerAccountForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LedgerAccountsTable::configure($table);
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
            'index' => ListLedgerAccounts::route('/'),
            'create' => CreateLedgerAccount::route('/create'),
            'edit' => EditLedgerAccount::route('/{record}/edit'),
        ];
    }
}
