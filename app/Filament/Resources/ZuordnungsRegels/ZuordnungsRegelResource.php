<?php

namespace App\Filament\Resources\ZuordnungsRegels;

use App\Filament\Resources\ZuordnungsRegels\Pages\CreateZuordnungsRegel;
use App\Filament\Resources\ZuordnungsRegels\Pages\EditZuordnungsRegel;
use App\Filament\Resources\ZuordnungsRegels\Pages\ListZuordnungsRegels;
use App\Filament\Resources\ZuordnungsRegels\Schemas\ZuordnungsRegelForm;
use App\Filament\Resources\ZuordnungsRegels\Tables\ZuordnungsRegelsTable;
use App\Models\ZuordnungsRegel;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZuordnungsRegelResource extends Resource
{
    protected static ?string $model = ZuordnungsRegel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Buchhaltung';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Zuordnungsregel';

    protected static ?string $pluralModelLabel = 'Zuordnungsregeln';

    public static function form(Schema $schema): Schema
    {
        return ZuordnungsRegelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZuordnungsRegelsTable::configure($table);
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
            'index' => ListZuordnungsRegels::route('/'),
            'create' => CreateZuordnungsRegel::route('/create'),
            'edit' => EditZuordnungsRegel::route('/{record}/edit'),
        ];
    }
}
