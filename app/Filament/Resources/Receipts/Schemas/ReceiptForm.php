<?php

namespace App\Filament\Resources\Receipts\Schemas;

use App\Enums\OcrStatus;
use App\Enums\ReceiptStatus;
use App\Enums\ReceiptType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReceiptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Datei & Typ')
                    ->columnSpan(1)
                    ->schema([
                        Select::make('type')
                            ->label('Belegart')
                            ->options(ReceiptType::class)
                            ->default(ReceiptType::IncomingInvoice->value)
                            ->required(),
                        FileUpload::make('file_path')
                            ->label('Belegdatei')
                            ->disk(config('pendelordner.belege_disk', 'belege'))
                            ->directory(date('Y/m'))
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/tiff'])
                            ->maxSize(20 * 1024)
                            ->downloadable()
                            ->openable()
                            ->helperText('PDF, JPG, PNG oder TIFF – max. 20 MB'),
                        Select::make('ocr_status')
                            ->label('OCR-Status')
                            ->options(OcrStatus::class)
                            ->default(OcrStatus::Pending->value),
                    ]),

                Section::make('Erkannte Rechnungsdaten')
                    ->description('Per OCR vorbefüllt – bitte prüfen und korrigieren.')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Lieferant')
                            ->relationship('supplier', 'name')
                            ->searchable()->preload()
                            ->createOptionForm([
                                TextInput::make('name')->required(),
                            ]),
                        TextInput::make('invoice_number')->label('Rechnungsnummer'),
                        DatePicker::make('invoice_date')
                            ->label('Rechnungsdatum')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('service_date')
                            ->label('Leistungsdatum')->native(false)->displayFormat('d.m.Y'),
                        TextInput::make('gross_amount')
                            ->label('Bruttobetrag')->numeric()->prefix('€')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                $rate = (float) ($get('tax_rate') ?? 0);
                                if ($state && $rate > 0) {
                                    $net = round($state / (1 + $rate / 100), 2);
                                    $set('net_amount', $net);
                                    $set('tax_amount', round($state - $net, 2));
                                }
                            }),
                        TextInput::make('tax_rate')->label('Steuersatz')->numeric()->suffix('%')->default(19),
                        TextInput::make('net_amount')->label('Nettobetrag')->numeric()->prefix('€'),
                        TextInput::make('tax_amount')->label('Steuerbetrag')->numeric()->prefix('€'),
                        TextInput::make('iban')->label('IBAN')->columnSpanFull(),
                    ]),

                Section::make('Zuordnung & Status')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        Select::make('business_id')->label('Betrieb')
                            ->relationship('business', 'name')->searchable()->preload(),
                        Select::make('category_id')->label('Kategorie')
                            ->relationship('category', 'name')->searchable()->preload(),
                        Select::make('cost_center_id')->label('Kostenstelle')
                            ->relationship('costCenter', 'name')->searchable()->preload(),
                        Select::make('status')->label('Status')
                            ->options(ReceiptStatus::class)->default(ReceiptStatus::New->value),
                        Toggle::make('paid')->label('Bezahlt')->inline(false),
                        Toggle::make('reviewed')->label('Geprüft')->inline(false),
                    ]),

                Section::make('OCR-Text & Notiz')
                    ->columnSpan(1)
                    ->collapsed()
                    ->schema([
                        Textarea::make('note')->label('Notiz')->rows(2),
                        Textarea::make('ocr_text')->label('OCR-Volltext')->rows(8),
                    ]),
            ]);
    }
}
