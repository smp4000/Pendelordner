<?php

namespace App\Filament\Resources\FintsConnections\Schemas;

use App\Models\BankPreset;
use Filament\Forms\Components\Select;
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
                        Select::make('bank_preset')
                            ->label('Bank-Vorlage')
                            ->options(BankPreset::orderBy('sort_order')->pluck('name', 'id'))
                            ->searchable()
                            ->dehydrated(false) // reines Hilfsfeld, nicht speichern
                            ->live()
                            ->helperText('Optional: Bank wählen, dann werden URL und HBCI-Version automatisch eingetragen.')
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $preset = BankPreset::find($state);
                                if (! $preset) {
                                    return;
                                }
                                $set('fints_url', $preset->fints_url);
                                $set('hbci_version', $preset->hbci_version);
                                if (blank($get('label'))) {
                                    $set('label', $preset->name);
                                }
                            })
                            ->columnSpanFull(),
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
