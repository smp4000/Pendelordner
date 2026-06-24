<?php

namespace App\Filament\Resources\CostCenters\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CostCenterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('number')->label('KOST1-Nummer'),
                Select::make('business_id')->label('Betrieb')
                    ->relationship('business', 'name')->searchable()->preload(),
                ColorPicker::make('color')->label('Farbe'),
                TextInput::make('sort_order')->label('Sortierung')->numeric()->default(0),
                Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                Textarea::make('description')->label('Beschreibung')->rows(2)->columnSpanFull(),
            ]);
    }
}
