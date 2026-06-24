<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('display_name')
                    ->default(null),
                Select::make('default_category_id')
                    ->relationship('defaultCategory', 'name')
                    ->default(null),
                Select::make('default_cost_center_id')
                    ->relationship('defaultCostCenter', 'name')
                    ->default(null),
                Select::make('default_business_id')
                    ->relationship('defaultBusiness', 'name')
                    ->default(null),
                TextInput::make('iban')
                    ->default(null),
                TextInput::make('bic')
                    ->default(null),
                TextInput::make('vat_id')
                    ->default(null),
                TextInput::make('tax_number')
                    ->default(null),
                TextInput::make('creditor_number')
                    ->default(null),
                TextInput::make('debtor_number')
                    ->default(null),
                TextInput::make('skr03_account')
                    ->default(null),
                TextInput::make('skr04_account')
                    ->default(null),
                TextInput::make('tax_key')
                    ->default(null),
                TextInput::make('street')
                    ->default(null),
                TextInput::make('postal_code')
                    ->default(null),
                TextInput::make('city')
                    ->default(null),
                Toggle::make('active')
                    ->required(),
                Textarea::make('note')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
