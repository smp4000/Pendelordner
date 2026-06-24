<?php

namespace App\Filament\Resources\Lieferants;

use App\Filament\Resources\Lieferants\Pages\CreateLieferant;
use App\Filament\Resources\Lieferants\Pages\EditLieferant;
use App\Filament\Resources\Lieferants\Pages\ListLieferants;
use App\Filament\Resources\Lieferants\Schemas\LieferantForm;
use App\Filament\Resources\Lieferants\Tables\LieferantsTable;
use App\Models\Lieferant;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LieferantResource extends Resource
{
    protected static ?string $model = Lieferant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Lieferant';

    protected static ?string $pluralModelLabel = 'Lieferanten';

    public static function form(Schema $schema): Schema
    {
        return LieferantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LieferantsTable::configure($table);
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
            'index' => ListLieferants::route('/'),
            'create' => CreateLieferant::route('/create'),
            'edit' => EditLieferant::route('/{record}/edit'),
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
