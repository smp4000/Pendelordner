<?php

namespace App\Filament\Resources\ImportLogs;

use App\Filament\Resources\ImportLogs\Pages\ListImportLogs;
use App\Filament\Resources\ImportLogs\Tables\ImportLogsTable;
use App\Models\ImportLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Import-Protokoll (Modul 1) – nur Ansicht. Zeigt je Import: Zeitpunkt, Konto,
 * Quelle (CSV/MT940/CAMT/FinTS), Gesamt/Neu/Dubletten/Fehler und Status.
 */
class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Import-Protokoll';

    protected static ?string $pluralModelLabel = 'Import-Protokoll';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ImportLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportLogs::route('/'),
        ];
    }
}
