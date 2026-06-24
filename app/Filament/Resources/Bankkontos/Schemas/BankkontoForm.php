<?php

namespace App\Filament\Resources\Bankkontos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BankkontoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('betrieb_id')
                    ->relationship('betrieb', 'name')
                    ->default(null),
                Select::make('fints_zugang_id')
                    ->relationship('fintsZugang', 'id')
                    ->default(null),
                TextInput::make('bezeichnung')
                    ->required(),
                TextInput::make('bank_name')
                    ->default(null),
                TextInput::make('iban')
                    ->default(null),
                TextInput::make('bic')
                    ->default(null),
                TextInput::make('kontonummer')
                    ->default(null),
                TextInput::make('blz')
                    ->default(null),
                TextInput::make('waehrung')
                    ->required()
                    ->default('EUR'),
                TextInput::make('saldo')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('saldo_datum'),
                Toggle::make('fints_aktiv')
                    ->required(),
                Toggle::make('aktiv')
                    ->required(),
                DateTimePicker::make('letzter_abruf_at'),
                TextInput::make('farbe')
                    ->default(null),
                Textarea::make('notiz')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
