<?php

namespace App\Filament\Resources\FintsConnections;

use App\Filament\Resources\FintsConnections\Pages\CreateFintsConnection;
use App\Filament\Resources\FintsConnections\Pages\EditFintsConnection;
use App\Filament\Resources\FintsConnections\Pages\ListFintsConnections;
use App\Filament\Resources\FintsConnections\Schemas\FintsConnectionForm;
use App\Filament\Resources\FintsConnections\Tables\FintsConnectionsTable;
use App\Models\FintsConnection;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FintsConnectionResource extends Resource
{
    protected static ?string $model = FintsConnection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'FinTS-Zugang';

    protected static ?string $pluralModelLabel = 'FinTS-Zugänge';

    public static function form(Schema $schema): Schema
    {
        return FintsConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FintsConnectionsTable::configure($table);
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
            'index' => ListFintsConnections::route('/'),
            'create' => CreateFintsConnection::route('/create'),
            'edit' => EditFintsConnection::route('/{record}/edit'),
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
