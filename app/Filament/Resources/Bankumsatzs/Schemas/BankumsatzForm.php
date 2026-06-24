<?php

namespace App\Filament\Resources\Bankumsatzs\Schemas;

use App\Enums\BankumsatzStatus;
use App\Enums\ImportQuelle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankumsatzForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Umsatzdaten')
                    ->description('Daten laut Bank – beim Import automatisch befüllt.')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('bankkonto_id')
                            ->label('Bankkonto')
                            ->relationship('bankkonto', 'bezeichnung')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('import_quelle')
                            ->label('Importquelle')
                            ->options(ImportQuelle::class)
                            ->default(ImportQuelle::Manuell->value),
                        DatePicker::make('buchungsdatum')
                            ->label('Buchungsdatum')
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->required(),
                        DatePicker::make('valutadatum')
                            ->label('Valutadatum')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        TextInput::make('empfaenger')
                            ->label('Empfänger / Auftraggeber')
                            ->columnSpanFull(),
                        Textarea::make('verwendungszweck')
                            ->label('Verwendungszweck')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('betrag')
                            ->label('Betrag')
                            ->numeric()
                            ->required()
                            ->prefix('€')
                            ->helperText('Negativ = Ausgang, positiv = Eingang'),
                        TextInput::make('empfaenger_iban')
                            ->label('IBAN Gegenseite'),
                        TextInput::make('bank_referenz')
                            ->label('Bankreferenz'),
                        TextInput::make('saldo_nach')
                            ->label('Saldo nach Buchung')
                            ->numeric()
                            ->prefix('€'),
                    ]),

                Section::make('Zuordnung & Status')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('betrieb_id')
                            ->label('Betrieb')
                            ->relationship('betrieb', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('lieferant_id')
                            ->label('Lieferant')
                            ->relationship('lieferant', 'name')
                            ->searchable()
                            ->preload()
                            // Beim Wählen eines Lieferanten dessen Defaults vorschlagen
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                $lieferant = \App\Models\Lieferant::find($state);
                                if ($lieferant?->standard_kategorie_id) {
                                    $set('kategorie_id', $lieferant->standard_kategorie_id);
                                }
                                if ($lieferant?->standard_kostenstelle_id) {
                                    $set('kostenstelle_id', $lieferant->standard_kostenstelle_id);
                                }
                            })
                            ->live(),
                        Select::make('kategorie_id')
                            ->label('Kategorie')
                            ->relationship('kategorie', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('kostenstelle_id')
                            ->label('Kostenstelle')
                            ->relationship('kostenstelle', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->label('Status')
                            ->options(BankumsatzStatus::class)
                            ->default(BankumsatzStatus::Offen->value)
                            ->required(),
                        Toggle::make('geprueft')
                            ->label('Geprüft')
                            ->inline(false),
                        Toggle::make('vollstaendig_bezahlt')
                            ->label('Vollständig bezahlt')
                            ->inline(false),
                        Textarea::make('notiz')
                            ->label('Notiz')
                            ->rows(2),
                    ]),
            ]);
    }
}
