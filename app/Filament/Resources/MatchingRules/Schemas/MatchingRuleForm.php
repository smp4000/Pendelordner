<?php

namespace App\Filament\Resources\MatchingRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MatchingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('pattern')
                    ->required(),
                TextInput::make('pattern_type')
                    ->required()
                    ->default('counterparty'),
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->default(null),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->default(null),
                Select::make('cost_center_id')
                    ->relationship('costCenter', 'name')
                    ->default(null),
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->default(null),
                TextInput::make('skr03_account')
                    ->default(null),
                TextInput::make('skr04_account')
                    ->default(null),
                TextInput::make('tax_key')
                    ->default(null),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('hit_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('active')
                    ->required(),
            ]);
    }
}
