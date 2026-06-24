<?php

namespace App\Filament\Resources\Kostenstelles;

use App\Filament\Resources\Kostenstelles\Pages\CreateKostenstelle;
use App\Filament\Resources\Kostenstelles\Pages\EditKostenstelle;
use App\Filament\Resources\Kostenstelles\Pages\ListKostenstelles;
use App\Filament\Resources\Kostenstelles\Schemas\KostenstelleForm;
use App\Filament\Resources\Kostenstelles\Tables\KostenstellesTable;
use App\Models\Kostenstelle;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KostenstelleResource extends Resource
{
    protected static ?string $model = Kostenstelle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Kostenstelle';

    protected static ?string $pluralModelLabel = 'Kostenstellen';

    public static function form(Schema $schema): Schema
    {
        return KostenstelleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KostenstellesTable::configure($table);
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
            'index' => ListKostenstelles::route('/'),
            'create' => CreateKostenstelle::route('/create'),
            'edit' => EditKostenstelle::route('/{record}/edit'),
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
