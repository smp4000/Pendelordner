<?php

namespace App\Filament\Resources\FintsZugangs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FintsZugangForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('bezeichnung')
                    ->required(),
                TextInput::make('bank_code')
                    ->required(),
                TextInput::make('fints_url')
                    ->url()
                    ->required(),
                TextInput::make('hbci_version')
                    ->required()
                    ->default('300'),
                TextInput::make('benutzerkennung')
                    ->required(),
                Textarea::make('pin')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('tan_verfahren')
                    ->default(null),
                TextInput::make('tan_medium')
                    ->default(null),
                TextInput::make('produkt_id')
                    ->default(null),
                TextInput::make('produkt_version')
                    ->required()
                    ->default('1.0'),
                Toggle::make('aktiv')
                    ->required(),
                DateTimePicker::make('letzter_abruf_at'),
                Textarea::make('letzte_meldung')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
