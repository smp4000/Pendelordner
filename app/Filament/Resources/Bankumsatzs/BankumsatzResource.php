<?php

namespace App\Filament\Resources\Bankumsatzs;

use App\Filament\Resources\Bankumsatzs\Pages\CreateBankumsatz;
use App\Filament\Resources\Bankumsatzs\Pages\EditBankumsatz;
use App\Filament\Resources\Bankumsatzs\Pages\ListBankumsatzs;
use App\Filament\Resources\Bankumsatzs\RelationManagers\BelegeRelationManager;
use App\Filament\Resources\Bankumsatzs\Schemas\BankumsatzForm;
use App\Filament\Resources\Bankumsatzs\Tables\BankumsatzsTable;
use App\Models\Bankumsatz;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class BankumsatzResource extends Resource
{
    protected static ?string $model = Bankumsatz::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Bankumsatz';

    protected static ?string $pluralModelLabel = 'Bankumsätze';

    protected static ?string $recordTitleAttribute = 'empfaenger';

    public static function form(Schema $schema): Schema
    {
        return BankumsatzForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankumsatzsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            BelegeRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankumsatzs::route('/'),
            'create' => CreateBankumsatz::route('/create'),
            'edit' => EditBankumsatz::route('/{record}/edit'),
        ];
    }

    /** Anzahl der Ausgaben ohne Beleg als Navigations-Badge (Rot). */
    public static function getNavigationBadge(): ?string
    {
        $offen = static::getModel()::query()->ohneBeleg()->where('betrag', '<', 0)->count();

        return $offen > 0 ? (string) $offen : null;
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
