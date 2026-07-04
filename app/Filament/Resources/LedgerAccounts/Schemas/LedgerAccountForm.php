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
                Select::make('tax_rate')
                    ->label('USt-Satz')
                    ->helperText('Wird beim Aufteilen automatisch übernommen. Leer = kein Standard.')
                    ->options([
                        '19' => '19 %',
                        '7' => '7 %',
                        '0' => '0 % / steuerfrei',
                    ])
                    // Gespeichert wird decimal ("19.00"); für die Option auf "19" normalisieren.
                    ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : (string) (int) $state)
                    ->placeholder('– kein Standard –'),
                Toggle::make('active')
                    ->label('Aktiv')
                    ->default(true),
            ]);
    }
}
