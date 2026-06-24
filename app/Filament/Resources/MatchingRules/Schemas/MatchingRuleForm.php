<?php

namespace App\Filament\Resources\MatchingRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MatchingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Regel')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pattern')->label('Muster')->required()
                            ->helperText('Suchbegriff, z. B. "HBW", "PAPPERT", "TELEKOM"'),
                        Select::make('pattern_type')->label('Muster-Typ')
                            ->options([
                                'counterparty' => 'Empfänger/Auftraggeber',
                                'purpose' => 'Verwendungszweck',
                                'iban' => 'IBAN',
                                'any' => 'Beliebig',
                            ])
                            ->default('counterparty')->required(),
                        TextInput::make('priority')->label('Priorität')->numeric()->default(0),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                    ]),

                Section::make('Zuordnung')
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')->label('Lieferant')
                            ->relationship('supplier', 'name')->searchable()->preload(),
                        Select::make('category_id')->label('Kategorie')
                            ->relationship('category', 'name')->searchable()->preload(),
                        Select::make('cost_center_id')->label('Kostenstelle')
                            ->relationship('costCenter', 'name')->searchable()->preload(),
                        Select::make('business_id')->label('Betrieb')
                            ->relationship('business', 'name')->searchable()->preload(),
                    ]),

                Section::make('Kontierung')
                    ->columns(3)
                    ->schema([
                        TextInput::make('skr03_account')->label('SKR03-Konto'),
                        TextInput::make('skr04_account')->label('SKR04-Konto'),
                        TextInput::make('tax_key')->label('Steuerschlüssel'),
                    ]),
            ]);
    }
}
