<?php

namespace App\Filament\Resources\BankAccounts\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('label')->label('Bezeichnung')->required(),
                        Select::make('business_id')->label('Betrieb')
                            ->relationship('business', 'name')->searchable()->preload(),
                        TextInput::make('bank_name')->label('Bank'),
                        TextInput::make('iban')->label('IBAN'),
                        TextInput::make('bic')->label('BIC'),
                        TextInput::make('account_number')->label('Kontonummer'),
                        TextInput::make('bank_code')->label('BLZ'),
                        TextInput::make('currency')->label('Währung')->default('EUR')->maxLength(3),
                    ]),

                Section::make('Saldo & FinTS')
                    ->columns(2)
                    ->schema([
                        TextInput::make('balance')->label('Saldo')->numeric()->prefix('€'),
                        DateTimePicker::make('balance_date')->label('Saldo-Datum')->native(false),
                        Select::make('fints_connection_id')->label('FinTS-Zugang')
                            ->relationship('fintsConnection', 'label')->searchable()->preload(),
                        Toggle::make('fints_enabled')->label('FinTS aktiv')->inline(false),
                    ]),

                Section::make('Sonstiges')
                    ->columns(2)
                    ->schema([
                        ColorPicker::make('color')->label('Farbe'),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                        Textarea::make('note')->label('Notiz')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
