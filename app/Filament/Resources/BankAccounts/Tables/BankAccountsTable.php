<?php

namespace App\Filament\Resources\BankAccounts\Tables;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Services\Bank\BankImportService;
use App\Services\Bank\FinTsService;
use App\Services\Bank\FinTsTanRequiredException;
use App\Services\Bank\Parsers\CamtParser;
use App\Services\Bank\Parsers\CsvBankParser;
use App\Services\Bank\Parsers\Mt940Parser;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BankAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Bezeichnung')->searchable(),
                TextColumn::make('bank_name')->label('Bank')->placeholder('—')->toggleable(),
                TextColumn::make('iban')->label('IBAN')->searchable()->placeholder('—'),
                TextColumn::make('business.name')->label('Betrieb')->badge()->placeholder('—'),
                TextColumn::make('balance')->label('Saldo')->money('EUR')->alignEnd()->placeholder('—'),
                TextColumn::make('last_fetched_at')->label('Letzter Abruf')->dateTime('d.m.Y H:i')->placeholder('—')->toggleable(),
                IconColumn::make('fints_enabled')->label('FinTS')->boolean()->alignCenter(),
                IconColumn::make('active')->label('Aktiv')->boolean()->alignCenter(),
            ])
            ->filters([
                TernaryFilter::make('fints_enabled')->label('FinTS aktiv'),
                TernaryFilter::make('active')->label('Aktiv'),
            ])
            ->recordActions([
                self::importAction(),
                self::fintsAction(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Datei-Import (MT940 / CAMT / CSV) mit Dublettenprüfung. */
    private static function importAction(): Action
    {
        return Action::make('import')
            ->label('Umsätze importieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('primary')
            ->schema([
                Select::make('format')
                    ->label('Format')
                    ->options([
                        'csv' => 'CSV',
                        'mt940' => 'MT940',
                        'camt' => 'CAMT.053 (XML)',
                    ])
                    ->default('csv')
                    ->required(),
                FileUpload::make('file')
                    ->label('Datei')
                    ->disk('local')
                    ->directory('imports/tmp')
                    ->storeFileNamesIn('original_name')
                    ->required(),
            ])
            ->action(function (array $data, BankAccount $record): void {
                $path = $data['file'];
                $content = Storage::disk('local')->get($path);

                // Deutsche Bank-CSVs sind häufig Windows-1252-kodiert.
                if (! mb_check_encoding($content, 'UTF-8')) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
                }

                try {
                    [$rows, $source] = match ($data['format']) {
                        'mt940' => [(new Mt940Parser())->parse($content), ImportSource::Mt940],
                        'camt' => [(new CamtParser())->parse($content), ImportSource::Camt],
                        default => [(new CsvBankParser())->parse($content), ImportSource::Csv],
                    };

                    $log = (new BankImportService())->import(
                        $record,
                        $rows,
                        $source,
                        $data['original_name'] ?? basename($path),
                    );

                    Notification::make()
                        ->title('Import abgeschlossen')
                        ->body("{$log->new_count} neu, {$log->duplicate_count} Dubletten, {$log->error_count} Fehler.")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Import fehlgeschlagen')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                } finally {
                    Storage::disk('local')->delete($path);
                }
            });
    }

    /** FinTS-Direktabruf der letzten 90 Tage. */
    private static function fintsAction(): Action
    {
        return Action::make('fints')
            ->label('FinTS abrufen')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (BankAccount $record): bool => $record->fints_enabled && $record->fints_connection_id)
            ->requiresConfirmation()
            ->modalDescription('Umsätze der letzten 90 Tage per FinTS abrufen?')
            ->action(function (BankAccount $record): void {
                try {
                    $log = (new FinTsService())->fetchAccount($record);
                    Notification::make()
                        ->title('FinTS-Abruf abgeschlossen')
                        ->body("{$log->new_count} neu, {$log->duplicate_count} Dubletten.")
                        ->success()
                        ->send();
                } catch (FinTsTanRequiredException $e) {
                    Notification::make()
                        ->title('TAN erforderlich')
                        ->body($e->getMessage())
                        ->warning()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('FinTS-Abruf fehlgeschlagen')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
