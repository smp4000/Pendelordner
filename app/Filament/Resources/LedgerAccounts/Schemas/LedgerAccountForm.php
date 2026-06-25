<?php

namespace App\Filament\Resources\LedgerAccounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LedgerAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('chart')
                    ->label('Kontenrahmen')
                    ->options([
                        'edtas' => 'edtas',
                        'kfz' => 'Kfz-Handel',
                        'gastro' => 'Gastronomie',
                    ])
                    ->default('edtas')
                    ->required(),
                TextInput::make('number')
                    ->label('Kontonummer')
                    ->required()
                    ->maxLength(20),
                TextInput::make('name')
                    ->label('Bezeichnung')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('group')
                    ->label('Zuordnung (GA)')
                    ->helperText('z. B. "B, Personalkosten"')
                    ->columnSpanFull(),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
