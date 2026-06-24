<?php

namespace App\Filament\Resources\Belegs\Schemas;

use App\Enums\BelegStatus;
use App\Enums\BelegTyp;
use App\Enums\OcrStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BelegForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Datei & Typ')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('typ')
                            ->label('Belegart')
                            ->options(BelegTyp::class)
                            ->default(BelegTyp::Rechnungseingang->value)
                            ->required(),
                        FileUpload::make('datei_pfad')
                            ->label('Belegdatei')
                            ->disk(config('pendelordner.belege_disk', 'belege'))
                            ->directory(date('Y/m'))
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'])
                            ->maxSize(20 * 1024) // 20 MB
                            ->downloadable()
                            ->openable()
                            ->helperText('PDF, JPG, PNG oder TIFF – max. 20 MB'),
                        Select::make('ocr_status')
                            ->label('OCR-Status')
                            ->options(OcrStatus::class)
                            ->default(OcrStatus::Ausstehend->value),
                    ]),

                Section::make('Erkannte Rechnungsdaten')
                    ->description('Per OCR vorbefüllt – bitte prüfen und korrigieren.')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('lieferant_id')
                            ->label('Lieferant')
                            ->relationship('lieferant', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                            ]),
                        TextInput::make('rechnungsnummer')
                            ->label('Rechnungsnummer'),
                        DatePicker::make('rechnungsdatum')
                            ->label('Rechnungsdatum')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('leistungsdatum')
                            ->label('Leistungsdatum')
                            ->native(false)
                            ->displayFormat('d.m.Y'),
                        TextInput::make('betrag_brutto')
                            ->label('Bruttobetrag')
                            ->numeric()->prefix('€')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                // Steuerbetrag aus Brutto und Satz ableiten
                                $satz = (float) ($get('steuersatz') ?? 0);
                                if ($state && $satz > 0) {
                                    $netto = round($state / (1 + $satz / 100), 2);
                                    $set('betrag_netto', $netto);
                                    $set('steuerbetrag', round($state - $netto, 2));
                                }
                            }),
                        TextInput::make('steuersatz')
                            ->label('Steuersatz')
                            ->numeric()->suffix('%')
                            ->default(19),
                        TextInput::make('betrag_netto')
                            ->label('Nettobetrag')
                            ->numeric()->prefix('€'),
                        TextInput::make('steuerbetrag')
                            ->label('Steuerbetrag')
                            ->numeric()->prefix('€'),
                        TextInput::make('iban')
                            ->label('IBAN')
                            ->columnSpanFull(),
                    ]),

                Section::make('Zuordnung & Status')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('betrieb_id')
                            ->label('Betrieb')
                            ->relationship('betrieb', 'name')
                            ->searchable()->preload(),
                        Select::make('kategorie_id')
                            ->label('Kategorie')
                            ->relationship('kategorie', 'name')
                            ->searchable()->preload(),
                        Select::make('kostenstelle_id')
                            ->label('Kostenstelle')
                            ->relationship('kostenstelle', 'name')
                            ->searchable()->preload(),
                        Select::make('status')
                            ->label('Status')
                            ->options(BelegStatus::class)
                            ->default(BelegStatus::Neu->value),
                        Toggle::make('bezahlt')->label('Bezahlt')->inline(false),
                        Toggle::make('geprueft')->label('Geprüft')->inline(false),
                    ]),

                Section::make('OCR-Text & Notiz')
                    ->columnSpan(1)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notiz')->label('Notiz')->rows(2),
                        Textarea::make('ocr_text')
                            ->label('OCR-Volltext')
                            ->rows(8),
                    ]),
            ]);
    }
}
