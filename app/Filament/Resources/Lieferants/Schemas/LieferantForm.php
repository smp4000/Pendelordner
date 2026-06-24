<?php

namespace App\Filament\Resources\Lieferants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LieferantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('anzeigename')
                    ->default(null),
                Select::make('standard_kategorie_id')
                    ->relationship('standardKategorie', 'name')
                    ->default(null),
                Select::make('standard_kostenstelle_id')
                    ->relationship('standardKostenstelle', 'name')
                    ->default(null),
                Select::make('standard_betrieb_id')
                    ->relationship('standardBetrieb', 'name')
                    ->default(null),
                TextInput::make('iban')
                    ->default(null),
                TextInput::make('bic')
                    ->default(null),
                TextInput::make('ust_id')
                    ->default(null),
                TextInput::make('steuernummer')
                    ->default(null),
                TextInput::make('kreditor_nummer')
                    ->default(null),
                TextInput::make('debitor_nummer')
                    ->default(null),
                TextInput::make('skr03_konto')
                    ->default(null),
                TextInput::make('skr04_konto')
                    ->default(null),
                TextInput::make('steuerschluessel')
                    ->default(null),
                TextInput::make('strasse')
                    ->default(null),
                TextInput::make('plz')
                    ->default(null),
                TextInput::make('ort')
                    ->default(null),
                Toggle::make('aktiv')
                    ->required(),
                Textarea::make('notiz')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
