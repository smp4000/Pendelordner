<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\LedgerAccount;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    /**
     * Auswahlfeld für ein Sachkonto aus dem Kontenrahmen – durchsuchbar und
     * direkt erweiterbar (neues Konto inline anlegen). Speichert die Kontonummer.
     */
    private static function ledgerSelect(string $field, string $label, string $defaultChart): Select
    {
        return Select::make($field)
            ->label($label)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search) => LedgerAccount::query()
                ->where(fn ($q) => $q->where('number', 'like', $search . '%')->orWhere('name', 'like', '%' . $search . '%'))
                ->orderBy('number')->limit(30)
                ->get()
                ->mapWithKeys(fn (LedgerAccount $la) => [$la->number => $la->number . ' – ' . $la->name . ' · ' . $la->chart])
                ->all())
            ->getOptionLabelUsing(fn ($value) => ($la = LedgerAccount::where('number', $value)->first())
                ? $la->number . ' – ' . $la->name
                : $value)
            ->createOptionForm([
                TextInput::make('number')->label('Kontonummer')->required(),
                TextInput::make('name')->label('Bezeichnung')->required(),
                Select::make('chart')->label('Kontenrahmen')
                    ->options(['skr03' => 'SKR03', 'skr04' => 'SKR04', 'edtas' => 'edtas'])
                    ->default($defaultChart)->required(),
            ])
            ->createOptionUsing(fn (array $data) => LedgerAccount::firstOrCreate(
                ['chart' => $data['chart'], 'number' => $data['number']],
                ['name' => $data['name']],
            )->number)
            ->helperText('Konto suchen oder mit „+" neu anlegen.');
    }

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
                        self::ledgerSelect('skr03_account', 'SKR03-Konto', 'skr03'),
                        self::ledgerSelect('skr04_account', 'SKR04-Konto', 'skr04'),
                        TextInput::make('tax_key')->label('Steuerschlüssel')
                            ->helperText('DATEV-BU, z. B. 9 = 19% VSt, 8 = 7% VSt'),
                        TextInput::make('default_tax_rate')->label('Standard-Steuersatz')->numeric()->suffix('%'),
                        Toggle::make('active')->label('Aktiv')->default(true)->inline(false),
                    ]),
            ]);
    }
}
