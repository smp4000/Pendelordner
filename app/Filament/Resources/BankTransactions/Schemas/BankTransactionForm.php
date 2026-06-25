<?php

namespace App\Filament\Resources\BankTransactions\Schemas;

use App\Enums\ImportSource;
use App\Enums\TransactionStatus;
use App\Models\Supplier;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BankTransactionForm
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
                        Select::make('bank_account_id')
                            ->label('Bankkonto')
                            ->relationship('bankAccount', 'label')
                            ->searchable()->preload()->required(),
                        Select::make('import_source')
                            ->label('Importquelle')
                            ->options(ImportSource::class)
                            ->default(ImportSource::Manual->value),
                        DatePicker::make('booking_date')
                            ->label('Buchungsdatum')
                            ->native(false)->displayFormat('d.m.Y')->required(),
                        DatePicker::make('value_date')
                            ->label('Valutadatum')
                            ->native(false)->displayFormat('d.m.Y'),
                        TextInput::make('counterparty')
                            ->label('Empfänger / Auftraggeber')
                            ->columnSpanFull(),
                        Textarea::make('purpose')
                            ->label('Verwendungszweck')
                            ->rows(2)->columnSpanFull(),
                        TextInput::make('amount')
                            ->label('Betrag')
                            ->numeric()->required()->prefix('€')
                            ->helperText('Negativ = Ausgang, positiv = Eingang'),
                        TextInput::make('counterparty_iban')
                            ->label('IBAN Gegenseite'),
                        TextInput::make('bank_reference')
                            ->label('Bankreferenz'),
                        TextInput::make('balance_after')
                            ->label('Saldo nach Buchung')
                            ->numeric()->prefix('€'),
                    ]),

                Section::make('Zuordnung & Status')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('business_id')
                            ->label('Betrieb')
                            ->relationship('business', 'name')
                            ->searchable()->preload(),
                        Select::make('supplier_id')
                            ->label('Lieferant')
                            ->relationship('supplier', 'name')
                            ->searchable()->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if (! $state) {
                                    return;
                                }
                                $supplier = Supplier::find($state);
                                if ($supplier?->default_category_id) {
                                    $set('category_id', $supplier->default_category_id);
                                }
                                if ($supplier?->default_cost_center_id) {
                                    $set('cost_center_id', $supplier->default_cost_center_id);
                                }
                            }),
                        Select::make('category_id')
                            ->label('Kategorie')
                            ->relationship('category', 'name')
                            ->searchable()->preload(),
                        Select::make('cost_center_id')
                            ->label('Kostenstelle')
                            ->relationship('costCenter', 'name')
                            ->searchable()->preload(),
                        Select::make('status')
                            ->label('Status')
                            ->options(TransactionStatus::class)
                            ->default(TransactionStatus::Open->value)
                            ->required(),
                        Toggle::make('reviewed')->label('Geprüft')->inline(false),
                        Toggle::make('fully_paid')->label('Vollständig bezahlt')->inline(false),
                        Textarea::make('note')->label('Notiz')->rows(2),
                        Textarea::make('accountant_note')
                            ->label('Mitteilung an den Steuerberater')
                            ->helperText('Erscheint fett unter dem Umsatz im Steuerberater-Bericht.')
                            ->rows(2),
                    ]),
            ]);
    }
}
