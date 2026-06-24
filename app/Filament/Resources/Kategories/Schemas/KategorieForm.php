<?php

namespace App\Filament\Resources\Kategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class KategorieForm
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
                Textarea::make('beschreibung')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('farbe')
                    ->default(null),
                TextInput::make('icon')
                    ->default(null),
                TextInput::make('skr03_konto')
                    ->default(null),
                TextInput::make('skr04_konto')
                    ->default(null),
                TextInput::make('steuerschluessel')
                    ->default(null),
                TextInput::make('standard_steuersatz')
                    ->numeric()
                    ->default(null),
                Toggle::make('aktiv')
                    ->required(),
                TextInput::make('sortierung')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
