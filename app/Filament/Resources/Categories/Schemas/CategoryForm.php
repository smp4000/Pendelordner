<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->relationship('parent', 'name')
                    ->default(null),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('color')
                    ->default(null),
                TextInput::make('icon')
                    ->default(null),
                TextInput::make('skr03_account')
                    ->default(null),
                TextInput::make('skr04_account')
                    ->default(null),
                TextInput::make('tax_key')
                    ->default(null),
                TextInput::make('default_tax_rate')
                    ->numeric()
                    ->default(null),
                Toggle::make('active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
