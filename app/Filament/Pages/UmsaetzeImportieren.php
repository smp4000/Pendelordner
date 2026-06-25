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

    /** Stehen neue Konten zur Benennung an? */
    public bool $awaitingNames = false;

    /**
     * Zu benennende neue Konten (eindeutig je Konto):
     * [ ['key' => ..., 'detected' => [...], 'name' => 'Vorschlag', 'hint' => '...'], ... ]
     *
     * @var array<int, array<string, mixed>>
     */
    public array $pendingAccounts = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Bankdateien (MT940 / CAMT / CSV)')
                    ->disk('local')
                    ->directory('imports/tmp')
                    ->storeFileNamesIn('original_name')
                    ->preserveFilenames(false)
                    ->multiple()
                    ->maxFiles(50)
                    ->required()
                    ->helperText('Mehrere Dateien möglich (z. B. .mta, .sta, .xml, .csv) – sie werden nacheinander verarbeitet und nach dem Import automatisch gelöscht.'),
                Select::make('bank_account_id')
                    ->label('Bankkonto')
                    ->placeholder('Automatisch aus Datei erkennen')
                    ->options(BankAccount::orderBy('label')->pluck('label', 'id'))
                    ->helperText('Leer lassen für automatische Erkennung je Datei. Bei CSV bitte das Zielkonto wählen.'),
            ])
            ->statePath('data');
    }

    public function importAction(): Action
    {
        return Action::make('import')
            ->label('Importieren')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn () => $this->runImport());
    }

    /** Wenn neue Dateien gewählt werden, den Benennungs-Schritt zurücksetzen. */
    public function updatedData($value, string $key): void
    {
        if ($key === 'file') {
            $this->awaitingNames = false;
            $this->pendingAccounts = [];
        }
    }

    /**
     * Schritt 1: Dateien prüfen. Werden neue Konten benötigt, zunächst editier-
     * bare Namensvorschläge anzeigen; sonst direkt importieren.
     */
    public function runImport(): void
    {
        $state = $this->form->getState();
        $files = $this->normalizeFiles($state);

        if (empty($files)) {
            Notification::make()->title('Keine Datei')->body('Bitte zuerst mindestens eine Datei hochladen.')->warning()->send();

            return;
        }

        $manual = ! empty($state['bank_account_id']) ? BankAccount::find($state['bank_account_id']) : null;

        // Neue (noch nicht vorhandene) Konten ermitteln und je Konto einmal
        // einen editierbaren Namensvorschlag sammeln.
        $pending = [];
        if (! $manual) {
            foreach ($files as [$path, $name]) {
                if (! Storage::disk('local')->exists($path)) {
                    continue;
                }
                $content = $this->readFile($path);
                if ($this->matchExistingAccount($content)) {
                    continue;
                }
                $detected = $this->detectAccount($content);
                if (! $detected) {
                    continue;
                }
                $key = $this->accountKey($content);
                if ($key && ! isset($pending[$key])) {
                    $pending[$key] = [
                        'key' => $key,
                        'detected' => $detected,
                        'name' => $detected['suggested'],
                        'hint' => $detected['iban']
                            ? 'IBAN ' . $detected['iban']
                            : 'Konto ' . $detected['account_number'] . ' / BLZ ' . $detected['bank_code'],
                    ];
                }
            }
        }

        if (! empty($pending)) {
            $this->pendingAccounts = array_values($pending);
            $this->awaitingNames = true;
            Notification::make()
                ->title('Neue Konten gefunden')
                ->body('Bitte die vorgeschlagenen Kontonamen prüfen und ggf. ändern.')
                ->info()->send();

            return;
        }

        // Keine neuen Konten nötig -> direkt importieren.
        $this->processFiles($files, $manual, []);
    }

    /** Schritt 2: Neue Konten mit den (ggf. geänderten) Namen anlegen, dann importieren. */
    public function confirmNames(): void
    {
        foreach ($this->pendingAccounts as $i => $acc) {
            if (trim((string) ($acc['name'] ?? '')) === '') {
                $this->addError('pendingAccounts.' . $i . '.name', 'Bitte einen Kontonamen vergeben.');

                return;
            }
        }

        $state = $this->form->getState();
        $files = $this->normalizeFiles($state);
        $manual = ! empty($state['bank_account_id']) ? BankAccount::find($state['bank_account_id']) : null;

        $createdMap = [];
        foreach ($this->pendingAccounts as $acc) {
            $d = $acc['detected'];
            $createdMap[$acc['key']] = BankAccount::create([
                'label' => trim((string) $acc['name']),
                'iban' => $d['iban'] ?? null,
                'bank_code' => $d['bank_code'] ?? null,
                'account_number' => $d['account_number'] ?? null,
                'business_id' => Business::orderBy('sort_order')->value('id'),
                'currency' => 'EUR',
                'active' => true,
            ]);
        }

        $this->awaitingNames = false;
        $this->pendingAccounts = [];

        $this->processFiles($files, $manual, $createdMap);
    }

    public function cancelNames(): void
    {
        $this->awaitingNames = false;
        $this->pendingAccounts = [];
    }

    // --- intern --------------------------------------------------------------

    /**
     * Importiert alle Dateien nacheinander. Konto je Datei: manuell gewählt,
     * sonst vorhandenes (per IBAN/BLZ), sonst eines der neu angelegten Konten.
     *
     * @param  array<int, array{0: string, 1: string}>  $files
     * @param  array<string, BankAccount>  $createdMap
     */
    private function processFiles(array $files, ?BankAccount $manual, array $createdMap): void
    {
        $lines = [];
        $sumNew = $sumDup = $sumErr = 0;

        foreach ($files as [$path, $name]) {
            try {
                if (! Storage::disk('local')->exists($path)) {
                    $lines[] = $name . ': Datei nicht gefunden';

                    continue;
                }

                $content = $this->readFile($path);
                [$rows, $source] = $this->parse($content);

                $account = $manual ?? $this->matchExistingAccount($content);
                if (! $account) {
                    $key = $this->accountKey($content);
                    $account = $key ? ($createdMap[$key] ?? null) : null;
                }

                if (! $account) {
                    $lines[] = $name . ': Konto nicht erkennbar – übersprungen';

                    continue;
                }

                $log = (new BankImportService())->import($account, $rows, $source, $name);

                $sumNew += $log->new_count;
                $sumDup += $log->duplicate_count;
                $sumErr += $log->error_count;

                $lines[] = sprintf(
                    '%s → %s: %d neu, %d Dubletten, %d Fehler',
                    $name, $account->label, $log->new_count, $log->duplicate_count, $log->error_count
                );
            } catch (Throwable $e) {
                report($e);
                $sumErr++;
                $lines[] = $name . ': Fehler – ' . $e->getMessage();
            } finally {
                Storage::disk('local')->delete($path);
            }
        }

        $this->form->fill();

        $body = implode("\n", $lines);
        if (! empty($createdMap)) {
            $body .= "\n\nNeu angelegte Konten: "
                . implode(', ', array_map(fn (BankAccount $a) => $a->label, $createdMap));
        }

        Notification::make()
            ->title(count($files) > 1
                ? count($files) . ' Dateien verarbeitet (' . $sumNew . ' neu, ' . $sumDup . ' Dubletten, ' . $sumErr . ' Fehler)'
                : 'Import abgeschlossen')
            ->body($body)
            ->success()
            ->persistent()
            ->send();
    }

    /** Eindeutiger Schlüssel eines Kontos aus dem Dateiinhalt (IBAN bzw. BLZ/Konto). */
    private function accountKey(string $content): ?string
    {
        $d = $this->detectAccount($content);
        if (! $d) {
            return null;
        }

        return $d['iban'] ?: ($d['bank_code'] . '/' . $d['account_number']);
    }

    /**
     * Bringt den FileUpload-State (einzeln oder mehrfach) auf eine einheitliche
     * Liste [[$pfad, $originalname], ...].
     *
     * @param  array<string, mixed>  $state
     * @return array<int, array{0: string, 1: string}>
     */
    private function normalizeFiles(array $state): array
    {
        $files = $state['file'] ?? [];
        if (is_string($files)) {
            $files = [$files];
        }

        $names = $state['original_name'] ?? [];

        $out = [];
        foreach ($files as $key => $path) {
            if (! $path) {
                continue;
            }
            $name = is_array($names) ? ($names[$key] ?? basename($path)) : (is_string($names) && $names !== '' ? $names : basename($path));
            $out[] = [$path, $name];
        }

        return $out;
    }

    /** Legt aus den in der Datei erkannten Kontodaten automatisch ein Konto an. */
    private function autoCreateAccount(string $content, array &$created): ?BankAccount
    {
        $detected = $this->detectAccount($content);
        if (! $detected) {
            return null;
        }

        $account = BankAccount::create([
            'label' => $detected['suggested'],
            'iban' => $detected['iban'] ?? null,
            'bank_code' => $detected['bank_code'] ?? null,
            'account_number' => $detected['account_number'] ?? null,
            'business_id' => Business::orderBy('sort_order')->value('id'),
            'currency' => 'EUR',
            'active' => true,
        ]);

        $created[] = $account;

        return $account;
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
