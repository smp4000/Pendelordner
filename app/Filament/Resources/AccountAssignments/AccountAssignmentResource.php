<?php

namespace App\Filament\Resources\AccountAssignments;

use App\Filament\Resources\AccountAssignments\Pages\CreateAccountAssignment;
use App\Filament\Resources\AccountAssignments\Pages\EditAccountAssignment;
use App\Filament\Resources\AccountAssignments\Pages\ListAccountAssignments;
use App\Filament\Resources\AccountAssignments\Schemas\AccountAssignmentForm;
use App\Filament\Resources\AccountAssignments\Tables\AccountAssignmentsTable;
use App\Models\AccountAssignment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AccountAssignmentResource extends Resource
{
    protected static ?string $model = AccountAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Buchhaltung';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Kontierung';

    protected static ?string $pluralModelLabel = 'Kontierungen';

    public static function form(Schema $schema): Schema
    {
        return AccountAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccountAssignmentsTable::configure($table);
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
            'index' => ListAccountAssignments::route('/'),
            'create' => CreateAccountAssignment::route('/create'),
            'edit' => EditAccountAssignment::route('/{record}/edit'),
        ];
    }
}
