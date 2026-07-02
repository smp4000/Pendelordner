<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Lieferant')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Name')->required(),
                        TextInput::make('display_name')->label('Anzeigename'),
                        TextInput::make('iban')->label('IBAN'),
                        TextInput::make('bic')->label('BIC'),
                        TextInput::make('vat_id')->label('USt-IdNr.'),
                        TextInput::make('tax_number')->label('Steuernummer'),
                    ]),

                Section::make('Tankstellen & Kundennummern')
                    ->description('Verknüpfe diesen Lieferanten mit deinen Tankstellen und hinterlege je Tankstelle die Kundennummer sowie Kostenstelle und eDTAS-Konto. Rechnungen dieser Kundennummer werden automatisch der Tankstelle zugeordnet und mit Kostenstelle/Konto vorbelegt.')
                    ->schema([
                        Repeater::make('customerNumbers')
                            ->relationship()
                            ->label('')
                            ->addActionLabel('Tankstelle verknüpfen')
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['customer_number'] ? 'Kd.-Nr. ' . $state['customer_number'] : null)
                            ->collapsible()
                            ->schema([
                                Select::make('business_id')->label('Tankstelle')
                                    ->relationship('business', 'name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_label)
                                    ->searchable(['name', 'postal_code', 'city'])->preload()->required(),
                                TextInput::make('customer_number')->label('Kundennummer'),
                                Select::make('cost_center_id')->label('Kostenstelle')
                                    ->relationship('costCenter', 'name')
                                    ->searchable()->preload()
                                    ->helperText('Wird bei Rechnungen dieser Kundennummer vorbelegt.'),
                                TextInput::make('edtas_account')->label('eDTAS-Konto')
                                    ->helperText('Konto für Rechnungen dieser Kundennummer (leer = Standard des Lieferanten).'),
                            ]),
                    ]),

                Section::make('Standard-Zuordnung')
                    ->description('Wird bei der Erfassung automatisch vorgeschlagen.')
                    ->columns(2)
                    ->schema([
                        Select::make('default_category_id')->label('Kategorie')
                            ->relationship('defaultCategory', 'name')->searchable()->preload(),
                        Select::make('default_cost_center_id')->label('Kostenstelle')
                            ->relationship('defaultCostCenter', 'name')->searchable()->preload(),
                        Select::make('default_business_id')->label('Betrieb')
                            ->relationship('defaultBusiness', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_label)
                            ->searchable(['name', 'postal_code', 'city'])->preload(),
                    ]),

                Section::make('Kontierung & DATEV')
                    ->columns(2)
                    ->schema([
                        TextInput::make('edtas_account')->label('eDTAS-Konto'),
                        TextInput::make('tax_key')->label('Steuerschlüssel'),
                        TextInput::make('creditor_number')->label('Kreditor-Nr.'),
                        TextInput::make('debtor_number')->label('Debitor-Nr.'),
                    ]),

                Section::make('Anschrift & Sonstiges')
                    ->columns(2)
                    ->schema([
                        TextInput::make('street')->label('Straße'),
                        TextInput::make('postal_code')->label('PLZ'),
                        TextInput::make('city')->label('Ort'),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                        Textarea::make('note')->label('Notiz')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
