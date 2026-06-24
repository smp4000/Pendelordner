<?php

namespace App\Filament\Resources\FintsConnections\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FintsConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bankzugang')
                    ->columns(2)
                    ->schema([
                        TextInput::make('label')->label('Bezeichnung')->required()
                            ->placeholder('z. B. VR Bank Fulda'),
                        TextInput::make('bank_code')->label('Bankleitzahl (BLZ)')->required(),
                        TextInput::make('fints_url')->label('FinTS-URL')->url()->columnSpanFull(),
                        TextInput::make('hbci_version')->label('HBCI-Version')->default('300'),
                    ]),

                Section::make('Anmeldung')
                    ->columns(2)
                    ->schema([
                        TextInput::make('username')->label('Benutzerkennung')->required(),
                        TextInput::make('pin')->label('PIN')
                            ->password()->revealable()
                            // Leeres Feld beim Bearbeiten überschreibt die gespeicherte PIN nicht.
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Wird verschlüsselt gespeichert.'),
                        TextInput::make('tan_method')->label('TAN-Verfahren'),
                        TextInput::make('tan_medium')->label('TAN-Medium'),
                    ]),

                Section::make('Produktregistrierung & Status')
                    ->columns(2)
                    ->schema([
                        TextInput::make('product_id')->label('Produkt-ID (FinTS-Registrierung)'),
                        TextInput::make('product_version')->label('Produktversion')->default('1.0'),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                    ]),
            ]);
    }
}
