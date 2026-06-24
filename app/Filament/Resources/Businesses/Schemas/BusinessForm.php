<?php

namespace App\Filament\Resources\Businesses\Schemas;

use App\Enums\BusinessType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BusinessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('short_name')
                    ->default(null),
                Select::make('type')
                    ->options(BusinessType::class)
                    ->default('gas_station')
                    ->required(),
                TextInput::make('street')
                    ->default(null),
                TextInput::make('postal_code')
                    ->default(null),
                TextInput::make('city')
                    ->default(null),
                TextInput::make('tax_number')
                    ->default(null),
                TextInput::make('vat_id')
                    ->default(null),
                TextInput::make('color')
                    ->default(null),
                Toggle::make('active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('note')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
