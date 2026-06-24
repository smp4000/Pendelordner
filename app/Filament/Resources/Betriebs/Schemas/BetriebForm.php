<?php

namespace App\Filament\Resources\Betriebs\Schemas;

use App\Enums\BetriebTyp;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BetriebForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('kurzname')
                    ->default(null),
                Select::make('typ')
                    ->options(BetriebTyp::class)
                    ->default('tankstelle')
                    ->required(),
                TextInput::make('strasse')
                    ->default(null),
                TextInput::make('plz')
                    ->default(null),
                TextInput::make('ort')
                    ->default(null),
                TextInput::make('steuernummer')
                    ->default(null),
                TextInput::make('ust_id')
                    ->default(null),
                TextInput::make('farbe')
                    ->default(null),
                Toggle::make('aktiv')
                    ->required(),
                TextInput::make('sortierung')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('notiz')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
