<?php

namespace App\Filament\Resources\Kostenstelles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class KostenstelleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('betrieb_id')
                    ->relationship('betrieb', 'name')
                    ->default(null),
                TextInput::make('nummer')
                    ->default(null),
                TextInput::make('name')
                    ->required(),
                Textarea::make('beschreibung')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('farbe')
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
