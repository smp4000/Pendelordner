<?php

namespace App\Filament\Resources\FintsConnections\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FintsConnectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required(),
                TextInput::make('bank_code')
                    ->required(),
                TextInput::make('fints_url')
                    ->url()
                    ->required(),
                TextInput::make('hbci_version')
                    ->required()
                    ->default('300'),
                TextInput::make('username')
                    ->required(),
                Textarea::make('pin')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('tan_method')
                    ->default(null),
                TextInput::make('tan_medium')
                    ->default(null),
                TextInput::make('product_id')
                    ->default(null),
                TextInput::make('product_version')
                    ->required()
                    ->default('1.0'),
                Toggle::make('active')
                    ->required(),
                DateTimePicker::make('last_fetched_at'),
                Textarea::make('last_message')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
