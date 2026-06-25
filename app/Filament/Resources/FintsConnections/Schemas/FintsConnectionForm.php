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
                        TextInput::make('fints_url')->label('FinTS-URL')->url()->columnSpanFull()
                            ->helperText('z. B. Commerzbank https://fints.commerzbank.de/fints, Volksbank (Fiducia) https://hbci11.fiducia.de/cgi-bin/hbciservlet'),
                        TextInput::make('hbci_version')->label('HBCI-Version')->default('300')
                            ->helperText('FinTS 3.0 = „300".'),
                    ]),

                Section::make('Anmeldung')
                    ->columns(2)
                    ->schema([
                        TextInput::make('username')->label('Benutzerkennung')->required()
                            ->helperText('Login-/Teilnehmernummer der Bank (kein Alias).'),
                        TextInput::make('customer_id')->label('Kunden-ID (optional)')
                            ->helperText('z. B. VR-Kennung bei Volksbanken; meist nicht nötig (wird automatisch ermittelt).'),
                        TextInput::make('pin')->label('PIN')
                            ->password()->revealable()
                            // Leeres Feld beim Bearbeiten überschreibt die gespeicherte PIN nicht.
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Wird verschlüsselt gespeichert.'),
                        TextInput::make('tan_method')->label('TAN-Verfahren')
                            ->helperText('Nummer des Verfahrens (z. B. pushTAN/App-Freigabe). Leer lassen, falls die App-Freigabe automatisch greift.'),
                        TextInput::make('tan_medium')->label('TAN-Medium')
                            ->helperText('Nur falls die Bank ein benanntes Medium verlangt.'),
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
