<?php

namespace App\Filament\Resources\Betriebs;

use App\Filament\Resources\Betriebs\Pages\CreateBetrieb;
use App\Filament\Resources\Betriebs\Pages\EditBetrieb;
use App\Filament\Resources\Betriebs\Pages\ListBetriebs;
use App\Filament\Resources\Betriebs\Schemas\BetriebForm;
use App\Filament\Resources\Betriebs\Tables\BetriebsTable;
use App\Models\Betrieb;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BetriebResource extends Resource
{
    protected static ?string $model = Betrieb::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Betrieb';

    protected static ?string $pluralModelLabel = 'Betriebe';

    public static function form(Schema $schema): Schema
    {
        return BetriebForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BetriebsTable::configure($table);
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
            'index' => ListBetriebs::route('/'),
            'create' => CreateBetrieb::route('/create'),
            'edit' => EditBetrieb::route('/{record}/edit'),
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
