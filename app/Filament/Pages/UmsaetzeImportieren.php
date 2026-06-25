<?php

namespace App\Filament\Pages;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\Business;
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
 * gelöscht. Format (MT940/CAMT/CSV) und – soweit ableitbar – das Konto werden
 * automatisch erkannt. Ist das Konto noch nicht vorhanden, wird nach einem
 * Kontonamen gefragt und das Konto angelegt.
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

    /** Steht ein neues Konto zur Benennung an? */
    public bool $awaitingName = false;

    /** @var array<string, mixed> erkannte Kontodaten aus der Datei */
    public array $pendingAccount = [];

    public string $newAccountName = '';

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
                    ->placeholder('Automatisch aus Datei erkennen')
                    ->options(BankAccount::orderBy('label')->pluck('label', 'id'))
                    ->helperText('Leer lassen für automatische Erkennung. Bei CSV bitte das Zielkonto wählen.'),
            ])
            ->statePath('data');
    }

    /** Wenn eine neue Datei gewählt wird, den Anlege-Dialog zurücksetzen. */
    public function updatedData($value, string $key): void
    {
        if ($key === 'file') {
            $this->awaitingName = false;
            $this->pendingAccount = [];
        }
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Importieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn () => $this->runImport());
    }

    /** Schritt 1: Datei prüfen, Konto ermitteln; ggf. nach Kontonamen fragen. */
    public function runImport(): void
    {
        $state = $this->form->getState();
        $path = $state['file'] ?? null;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            Notification::make()->title('Keine Datei')->body('Bitte zuerst eine Datei hochladen.')->warning()->send();

            return;
        }

        try {
            $content = $this->readFile($path);
            [$rows, $source] = $this->parse($content);

            // Manuell gewähltes Konto?
            if (! empty($state['bank_account_id'])) {
                $this->doImport(BankAccount::find($state['bank_account_id']), $rows, $source, $path, $state);

                return;
            }

            // Vorhandenes Konto automatisch erkennen
            if ($account = $this->matchExistingAccount($content)) {
                $this->doImport($account, $rows, $source, $path, $state);

                return;
            }

            // Konto aus Datei ableitbar -> nach Namen fragen (Datei bleibt liegen)
            if ($detected = $this->detectAccount($content)) {
                $this->pendingAccount = $detected;
                $this->newAccountName = $detected['suggested'];
                $this->awaitingName = true;
                Notification::make()
                    ->title('Neues Konto')
                    ->body('Dieses Konto ist noch nicht vorhanden. Bitte einen Kontonamen vergeben.')
                    ->info()->send();

                return;
            }

            Notification::make()
                ->title('Konto nicht erkennbar')
                ->body('Aus dieser Datei (CSV) ließ sich kein Konto ableiten. Bitte oben das Zielkonto wählen.')
                ->warning()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Import fehlgeschlagen')->body($e->getMessage())->danger()->send();
        }
    }

    /** Schritt 2: Neues Konto mit eingegebenem Namen anlegen und importieren. */
    public function confirmNewAccount(): void
    {
        $this->validate(
            ['newAccountName' => 'required|string|min:2|max:120'],
            [],
            ['newAccountName' => 'Kontoname']
        );

        $state = $this->form->getState();
        $path = $state['file'] ?? null;
        if (! $path || ! Storage::disk('local')->exists($path)) {
            Notification::make()->title('Datei nicht mehr vorhanden')->body('Bitte die Datei erneut hochladen.')->warning()->send();
            $this->awaitingName = false;

            return;
        }

        try {
            $content = $this->readFile($path);
            [$rows, $source] = $this->parse($content);

            $account = BankAccount::create([
                'label' => trim($this->newAccountName),
                'iban' => $this->pendingAccount['iban'] ?? null,
                'bank_code' => $this->pendingAccount['bank_code'] ?? null,
                'account_number' => $this->pendingAccount['account_number'] ?? null,
                'business_id' => Business::orderBy('sort_order')->value('id'),
                'currency' => 'EUR',
                'active' => true,
            ]);

            $this->awaitingName = false;
            $this->pendingAccount = [];

            $this->doImport($account, $rows, $source, $path, $state, neu: true);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Import fehlgeschlagen')->body($e->getMessage())->danger()->send();
        }
    }

    public function cancelNewAccount(): void
    {
        $this->awaitingName = false;
        $this->pendingAccount = [];
    }

    // --- intern --------------------------------------------------------------

    /** Führt den Import durch, löscht die Datei und setzt das Formular zurück. */
    private function doImport(?BankAccount $account, array $rows, ImportSource $source, string $path, array $state, bool $neu = false): void
    {
        if (! $account) {
            Notification::make()->title('Kein Konto')->warning()->send();

            return;
        }

        $log = (new BankImportService())->import($account, $rows, $source, $state['original_name'] ?? basename($path));

        Storage::disk('local')->delete($path);
        $this->form->fill();
        $this->reset(['awaitingName', 'pendingAccount', 'newAccountName']);

        Notification::make()
            ->title('Import abgeschlossen')
            ->body(($neu ? 'Neues Konto „' . $account->label . '" angelegt. ' : 'Konto ' . $account->label . ': ')
                . $log->new_count . ' neu, ' . $log->duplicate_count . ' Dubletten, ' . $log->error_count . ' Fehler.')
            ->success()->send();
    }

    private function readFile(string $path): string
    {
        $content = Storage::disk('local')->get($path);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        return $content;
    }

    /**
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

    /** Sucht ein bereits vorhandenes Konto anhand der Kontodaten in der Datei. */
    private function matchExistingAccount(string $content): ?BankAccount
    {
        if (preg_match('/:25:([0-9]+)\/([A-Z0-9]+)/', $content, $m)) {
            $trimmed = ltrim($m[2], '0');

            return BankAccount::query()
                ->where('bank_code', $m[1])
                ->where(function ($q) use ($m, $trimmed) {
                    $q->where('account_number', $m[2])
                        ->orWhereRaw('TRIM(LEADING ? FROM account_number) = ?', ['0', $trimmed]);
                })
                ->first();
        }

        if (preg_match('/<IBAN>([A-Z]{2}[0-9A-Z]+)<\/IBAN>/', $content, $m)) {
            return BankAccount::where('iban', $m[1])->first();
        }

        return null;
    }

    /**
     * Leitet Kontodaten + Namensvorschlag aus der Datei ab (für neues Konto).
     *
     * @return array<string, mixed>|null
     */
    private function detectAccount(string $content): ?array
    {
        if (preg_match('/:25:([0-9]+)\/([A-Z0-9]+)/', $content, $m)) {
            return [
                'bank_code' => $m[1],
                'account_number' => $m[2],
                'iban' => null,
                'suggested' => 'Konto ' . $m[2] . ' (BLZ ' . $m[1] . ')',
            ];
        }

        if (preg_match('/<IBAN>([A-Z]{2}[0-9A-Z]+)<\/IBAN>/', $content, $m)) {
            return [
                'bank_code' => substr($m[1], 4, 8),
                'account_number' => null,
                'iban' => $m[1],
                'suggested' => 'Konto ' . $m[1],
            ];
        }

        return null;
    }
}
