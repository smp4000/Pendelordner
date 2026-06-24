<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Kategorie')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Name')->required(),
                        Select::make('parent_id')->label('Übergeordnete Kategorie')
                            ->relationship('parent', 'name')->searchable()->preload(),
                        ColorPicker::make('color')->label('Farbe'),
                        TextInput::make('sort_order')->label('Sortierung')->numeric()->default(0),
                        Textarea::make('description')->label('Beschreibung')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Kontierung (SKR03/04)')
                    ->description('Vorbelegung für Buchhaltung/DATEV.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('skr03_account')->label('SKR03-Konto'),
                        TextInput::make('skr04_account')->label('SKR04-Konto'),
                        TextInput::make('tax_key')->label('Steuerschlüssel')
                            ->helperText('DATEV-BU, z. B. 9 = 19% VSt, 8 = 7% VSt'),
                        TextInput::make('default_tax_rate')->label('Standard-Steuersatz')->numeric()->suffix('%'),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                    ]),
            ]);
    }
}
