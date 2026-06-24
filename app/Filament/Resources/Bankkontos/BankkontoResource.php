<?php

namespace App\Filament\Resources\Bankkontos;

use App\Filament\Resources\Bankkontos\Pages\CreateBankkonto;
use App\Filament\Resources\Bankkontos\Pages\EditBankkonto;
use App\Filament\Resources\Bankkontos\Pages\ListBankkontos;
use App\Filament\Resources\Bankkontos\Schemas\BankkontoForm;
use App\Filament\Resources\Bankkontos\Tables\BankkontosTable;
use App\Models\Bankkonto;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BankkontoResource extends Resource
{
    protected static ?string $model = Bankkonto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Bankkonto';

    protected static ?string $pluralModelLabel = 'Bankkonten';

    public static function form(Schema $schema): Schema
    {
        return BankkontoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankkontosTable::configure($table);
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
            'index' => ListBankkontos::route('/'),
            'create' => CreateBankkonto::route('/create'),
            'edit' => EditBankkonto::route('/{record}/edit'),
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
