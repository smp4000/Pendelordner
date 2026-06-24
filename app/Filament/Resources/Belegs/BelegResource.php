<?php

namespace App\Filament\Resources\Belegs;

use App\Filament\Resources\Belegs\Pages\CreateBeleg;
use App\Filament\Resources\Belegs\Pages\EditBeleg;
use App\Filament\Resources\Belegs\Pages\ListBelegs;
use App\Filament\Resources\Belegs\Schemas\BelegForm;
use App\Filament\Resources\Belegs\Tables\BelegsTable;
use App\Models\Beleg;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BelegResource extends Resource
{
    protected static ?string $model = Beleg::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Belege';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Beleg';

    protected static ?string $pluralModelLabel = 'Belege';

    public static function form(Schema $schema): Schema
    {
        return BelegForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BelegsTable::configure($table);
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
            'index' => ListBelegs::route('/'),
            'create' => CreateBeleg::route('/create'),
            'edit' => EditBeleg::route('/{record}/edit'),
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
