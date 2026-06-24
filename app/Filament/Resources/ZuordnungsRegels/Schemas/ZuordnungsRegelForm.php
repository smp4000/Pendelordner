<?php

namespace App\Filament\Resources\ZuordnungsRegels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ZuordnungsRegelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('muster')
                    ->required(),
                TextInput::make('muster_typ')
                    ->required()
                    ->default('empfaenger'),
                Select::make('lieferant_id')
                    ->relationship('lieferant', 'name')
                    ->default(null),
                Select::make('kategorie_id')
                    ->relationship('kategorie', 'name')
                    ->default(null),
                Select::make('kostenstelle_id')
                    ->relationship('kostenstelle', 'name')
                    ->default(null),
                Select::make('betrieb_id')
                    ->relationship('betrieb', 'name')
                    ->default(null),
                TextInput::make('skr03_konto')
                    ->default(null),
                TextInput::make('skr04_konto')
                    ->default(null),
                TextInput::make('steuerschluessel')
                    ->default(null),
                TextInput::make('prioritaet')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('treffer_anzahl')
                    ->required()
                    ->numeric()
                    ->default(0),
                Toggle::make('aktiv')
                    ->required(),
            ]);
    }
}
