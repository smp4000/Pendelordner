<?php

namespace App\Filament\Resources\Kategories;

use App\Filament\Resources\Kategories\Pages\CreateKategorie;
use App\Filament\Resources\Kategories\Pages\EditKategorie;
use App\Filament\Resources\Kategories\Pages\ListKategories;
use App\Filament\Resources\Kategories\Schemas\KategorieForm;
use App\Filament\Resources\Kategories\Tables\KategoriesTable;
use App\Models\Kategorie;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KategorieResource extends Resource
{
    protected static ?string $model = Kategorie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Stammdaten';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kategorie';

    protected static ?string $pluralModelLabel = 'Kategorien';

    public static function form(Schema $schema): Schema
    {
        return KategorieForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KategoriesTable::configure($table);
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
            'index' => ListKategories::route('/'),
            'create' => CreateKategorie::route('/create'),
            'edit' => EditKategorie::route('/{record}/edit'),
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
