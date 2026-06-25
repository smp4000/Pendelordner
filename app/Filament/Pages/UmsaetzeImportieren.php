<?php

namespace App\Filament\Pages;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Services\Bank\BankImportService;
use App\Services\Bank\Parsers\CamtParser;
use App\Services\Bank\Parsers\CsvBankParser;
use App\Services\Bank\Parsers\Mt940Parser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Throwable;
use UnitEnum;

/**
 * Datei-Upload zum Import von Bankumsätzen (Modul 1). Die hochgeladene Datei
 * wird nur kurz zwischengespeichert, eingelesen und anschließend wieder
 * gelöscht. Format (MT940/CAMT/CSV) und – bei MT940 – das Konto werden
 * automatisch erkannt.
 */
class UmsaetzeImportieren extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected string $view = 'filament.pages.umsaetze-importieren';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 6;

    protected static ?string $title = 'Umsätze importieren';

    protected static ?string $navigationLabel = 'Umsätze importieren';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Bankdatei (MT940 / CAMT / CSV)')
                    ->disk('local')
                    ->directory('imports/tmp')
                    ->storeFileNamesIn('original_name')
                    ->preserveFilenames(false)
                    ->required()
                    ->helperText('Datei hierher ziehen oder auswählen (z. B. .mta, .sta, .xml, .csv). Wird nach dem Import automatisch gelöscht.'),
                Select::make('bank_account_id')
                    ->label('Bankkonto')
                    ->placeholder('Automatisch aus Datei erkennen (MT940)')
                    ->options(BankAccount::orderBy('label')->pluck('label', 'id'))
                    ->helperText('Bei CSV/CAMT bitte das Zielkonto wählen.'),
            ])
            ->statePath('data');
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Importieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (): void {
                $state = $this->form->getState();
                $path = $state['file'] ?? null;

                if (! $path || ! Storage::disk('local')->exists($path)) {
                    Notification::make()->title('Keine Datei')->body('Bitte zuerst eine Datei hochladen.')->warning()->send();

                    return;
                }

                try {
                    $content = Storage::disk('local')->get($path);
                    if (! mb_check_encoding($content, 'UTF-8')) {
                        $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
                    }

                    [$rows, $source] = $this->parse($content);

                    $account = $this->resolveAccount($state['bank_account_id'] ?? null, $content);
                    if (! $account) {
                        Notification::make()
                            ->title('Konto nicht erkannt')
                            ->body('Aus dieser CSV ließ sich kein Konto ableiten. Bitte das Zielkonto auswählen.')
                            ->warning()->send();

                        return;
                    }

                    $log = (new BankImportService())->import($account, $rows, $source, $state['original_name'] ?? basename($path));

                    Notification::make()
                        ->title('Import abgeschlossen')
                        ->body('Konto ' . $account->label . ': ' . $log->new_count . ' neu, ' . $log->duplicate_count . ' Dubletten, ' . $log->error_count . ' Fehler.')
                        ->success()->send();

                    $this->form->fill();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Import fehlgeschlagen')->body($e->getMessage())->danger()->send();
                } finally {
                    // Hochgeladene Datei in jedem Fall wieder löschen.
                    Storage::disk('local')->delete($path);
                }
            });
    }

    /**
     * Erkennt das Format am Inhalt und parst die Datei.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: ImportSource}
     */
    private function parse(string $content): array
    {
        $trimmed = ltrim($content);

        if (str_contains($content, ':20:') && str_contains($content, ':61:')) {
            return [(new Mt940Parser())->parse($content), ImportSource::Mt940];
        }
        if (str_starts_with($trimmed, '<?xml') || str_contains($content, 'camt.05') || str_contains($content, '<Ntry>')) {
            return [(new CamtParser())->parse($content), ImportSource::Camt];
        }

        return [(new CsvBankParser())->parse($content), ImportSource::Csv];
    }

    /**
     * Konto bestimmen: ausgewählt > automatisch aus der Datei erkennen und – falls
     * nicht vorhanden – automatisch anlegen. MT940 über :25: (BLZ/Kontonummer),
     * CAMT über die Konto-IBAN im Auszugskopf. Bei CSV nicht ableitbar -> null.
     */
    private function resolveAccount(?int $selectedId, string $content): ?BankAccount
    {
        if ($selectedId) {
            return BankAccount::find($selectedId);
        }

        // MT940: :25:BLZ/Kontonummer
        if (preg_match('/:25:([0-9]+)\/([A-Z0-9]+)/', $content, $m)) {
            $blz = $m[1];
            $account = $m[2];
            $trimmed = ltrim($account, '0');

            $existing = BankAccount::query()
                ->where('bank_code', $blz)
                ->where(function ($q) use ($account, $trimmed) {
                    $q->where('account_number', $account)
                        ->orWhereRaw('TRIM(LEADING ? FROM account_number) = ?', ['0', $trimmed]);
                })
                ->first();

            return $existing ?? $this->createAccount(
                bankCode: $blz,
                accountNumber: $account,
                label: 'Konto ' . $account . ' (BLZ ' . $blz . ')',
            );
        }

        // CAMT.053: erste IBAN im Dokument = Konto-IBAN des Auszugs
        if ((str_contains($content, 'camt.05') || str_contains($content, '<Ntry>'))
            && preg_match('/<IBAN>([A-Z]{2}[0-9A-Z]+)<\/IBAN>/', $content, $m)) {
            $iban = $m[1];

            $existing = BankAccount::where('iban', $iban)->first();

            return $existing ?? $this->createAccount(
                iban: $iban,
                bankCode: substr($iban, 4, 8), // dt. IBAN: Stellen 5–12 = BLZ
                label: 'Konto ' . $iban,
            );
        }

        return null;
    }

    /** Legt automatisch ein Bankkonto an (dem ersten Betrieb zugeordnet). */
    private function createAccount(
        ?string $iban = null,
        ?string $bankCode = null,
        ?string $accountNumber = null,
        string $label = 'Importiertes Konto',
    ): BankAccount {
        $account = BankAccount::create([
            'label' => $label,
            'iban' => $iban,
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'business_id' => \App\Models\Business::orderBy('sort_order')->value('id'),
            'currency' => 'EUR',
            'active' => true,
        ]);

        Notification::make()
            ->title('Neues Bankkonto angelegt')
            ->body('„' . $label . '" wurde automatisch erstellt.')
            ->success()->send();

        return $account;
    }
}
