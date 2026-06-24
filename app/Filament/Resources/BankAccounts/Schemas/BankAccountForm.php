<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('business_id')
                    ->relationship('business', 'name')
                    ->default(null),
                Select::make('fints_connection_id')
                    ->relationship('fintsConnection', 'id')
                    ->default(null),
                TextInput::make('label')
                    ->required(),
                TextInput::make('bank_name')
                    ->default(null),
                TextInput::make('iban')
                    ->default(null),
                TextInput::make('bic')
                    ->default(null),
                TextInput::make('account_number')
                    ->default(null),
                TextInput::make('bank_code')
                    ->default(null),
                TextInput::make('currency')
                    ->required()
                    ->default('EUR'),
                TextInput::make('balance')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('balance_date'),
                Toggle::make('fints_enabled')
                    ->required(),
                Toggle::make('active')
                    ->required(),
                DateTimePicker::make('last_fetched_at'),
                TextInput::make('color')
                    ->default(null),
                Textarea::make('note')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
