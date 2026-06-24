<?php

namespace App\Filament\Resources\FintsZugangs;

use App\Filament\Resources\FintsZugangs\Pages\CreateFintsZugang;
use App\Filament\Resources\FintsZugangs\Pages\EditFintsZugang;
use App\Filament\Resources\FintsZugangs\Pages\ListFintsZugangs;
use App\Filament\Resources\FintsZugangs\Schemas\FintsZugangForm;
use App\Filament\Resources\FintsZugangs\Tables\FintsZugangsTable;
use App\Models\FintsZugang;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FintsZugangResource extends Resource
{
    protected static ?string $model = FintsZugang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'FinTS-Zugang';

    protected static ?string $pluralModelLabel = 'FinTS-Zugänge';

    public static function form(Schema $schema): Schema
    {
        return FintsZugangForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FintsZugangsTable::configure($table);
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
            'index' => ListFintsZugangs::route('/'),
            'create' => CreateFintsZugang::route('/create'),
            'edit' => EditFintsZugang::route('/{record}/edit'),
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
