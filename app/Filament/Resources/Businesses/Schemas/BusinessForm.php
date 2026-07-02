<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Enums\BusinessType;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Betrieb')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Name')->required()->columnSpanFull(),
                        TextInput::make('short_name')->label('Kurzname'),
                        Select::make('type')->label('Betriebsart')
                            ->options(BusinessType::class)
                            ->default('gas_station')->required(),
                    ]),

                Section::make('Anschrift & Kontakt')
                    ->columns(2)
                    ->schema([
                        TextInput::make('street')->label('Straße')->columnSpanFull(),
                        TextInput::make('postal_code')->label('PLZ'),
                        TextInput::make('city')->label('Ort'),
                        TextInput::make('phone')->label('Telefon')->tel(),
                        TextInput::make('fax')->label('Fax'),
                        TextInput::make('email')->label('E-Mail')->email()->columnSpanFull(),
                    ]),

                Section::make('Kasse / Tankstelle')
                    ->columns(2)
                    ->schema([
                        TextInput::make('station_number')->label('Stationsnummer')
                            ->helperText('Aral-Stationsnummer – für die Zuordnung der Kassenabrechnung.'),
                        TextInput::make('fuel_commission_ct')->label('Kraftstoff-Provision (Cent/Liter)')
                            ->numeric()->default(2.8)
                            ->helperText('Provision je Liter für die Ist-Erlöse.'),
                    ]),

                Section::make('Steuer & Sonstiges')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tax_number')->label('Steuernummer'),
                        TextInput::make('vat_id')->label('USt-IdNr.'),
                        ColorPicker::make('color')->label('Farbe'),
                        TextInput::make('sort_order')->label('Sortierung')->numeric()->default(0),
                        Toggle::make('active')->label('Aktiv')->default(true),
                        Textarea::make('note')->label('Notiz')->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
