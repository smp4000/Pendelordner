<?php

namespace App\Filament\Resources\MatchingRules;

use App\Filament\Resources\MatchingRules\Pages\CreateMatchingRule;
use App\Filament\Resources\MatchingRules\Pages\EditMatchingRule;
use App\Filament\Resources\MatchingRules\Pages\ListMatchingRules;
use App\Filament\Resources\MatchingRules\Schemas\MatchingRuleForm;
use App\Filament\Resources\MatchingRules\Tables\MatchingRulesTable;
use App\Models\MatchingRule;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MatchingRuleResource extends Resource
{
    protected static ?string $model = MatchingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Buchhaltung';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Zuordnungsregel';

    protected static ?string $pluralModelLabel = 'Zuordnungsregeln';

    public static function form(Schema $schema): Schema
    {
        return MatchingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MatchingRulesTable::configure($table);
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
            'index' => ListMatchingRules::route('/'),
            'create' => CreateMatchingRule::route('/create'),
            'edit' => EditMatchingRule::route('/{record}/edit'),
        ];
    }
}
