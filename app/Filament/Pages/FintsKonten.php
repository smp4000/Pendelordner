<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\FintsConnection;
use App\Services\Bank\FinTsErrorTranslator;
use App\Services\Bank\FinTsService;
use App\Services\Bank\FinTsTanRequiredException;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

/**
 * Interaktiver FinTS-Ablauf (Modul 1) speziell für Banken mit TAN-Pflicht
 * (z. B. VR Bank): Zugang wählen → Konten abrufen → auswählen & speichern →
 * Umsätze abrufen. TAN-Abfragen werden inline behandelt; der sensible
 * Fortsetzungszustand liegt serverseitig in der Session.
 */
class FintsKonten extends Page
{
    protected string $view = 'filament.pages.fints-konten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'FinTS-Konten abrufen';

    protected static ?string $navigationLabel = 'FinTS-Konten abrufen';

    public ?int $connectionId = null;

    /** Zur Laufzeit eingegebene PIN (leer = im Zugang gespeicherte PIN nutzen). */
    public string $pin = '';

    /** idle | tan | accounts */
    public string $step = 'idle';

    /** @var array<int, array<string, mixed>> */
    public array $discovered = [];

    /** @var array<int, string> ausgewählte IBANs */
    public array $selected = [];

    public string $tan = '';

    public string $tanChallenge = '';

    /** true = App-Freigabe (Decoupled), keine TAN-Eingabe nötig. */
    public bool $tanDecoupled = false;

    private const SESSION_KEY = 'fints.pending';

    public function mount(): void
    {
        $this->connectionId = FintsConnection::where('active', true)->value('id');
    }

    public function updatedConnectionId(): void
    {
        $this->reset(['step', 'discovered', 'selected', 'tan', 'tanChallenge', 'tanDecoupled']);
        session()->forget(self::SESSION_KEY);
    }

    public function getConnectionsProperty(): Collection
    {
        return FintsConnection::where('active', true)->orderBy('label')->pluck('label', 'id');
    }

    public function getSavedAccountsProperty(): Collection
    {
        if (! $this->connectionId) {
            return collect();
        }

        return BankAccount::where('fints_connection_id', $this->connectionId)->orderBy('label')->get();
    }

    /** Konten beim Zugang abrufen. */
    public function discover(): void
    {
        $connection = FintsConnection::find($this->connectionId);
        if (! $connection) {
            $this->notifyError('Bitte zuerst einen FinTS-Zugang auswählen.');

            return;
        }

        try {
            $accounts = (new FinTsService())->discoverAccounts($connection, $this->pin ?: null);
            $this->showAccounts($accounts);
        } catch (FinTsTanRequiredException $e) {
            $this->requestTan($e);
        } catch (Throwable $e) {
            report($e);
            $this->notifyError($e);
        }
    }

    /** Umsätze eines gespeicherten Kontos abrufen. */
    public function fetch(int $accountId): void
    {
        $account = BankAccount::find($accountId);
        if (! $account) {
            return;
        }

        try {
            $log = (new FinTsService())->fetchAccount($account, null, null, $this->pin ?: null);
            $this->notifyImport($log);
        } catch (FinTsTanRequiredException $e) {
            $this->requestTan($e);
        } catch (Throwable $e) {
            report($e);
            $this->notifyError($e);
        }
    }

    /** Eingegebene TAN absenden und Vorgang fortsetzen. */
    public function submitTan(): void
    {
        $pending = session(self::SESSION_KEY);
        if (! $pending) {
            $this->step = 'idle';

            return;
        }

        try {
            $result = (new FinTsService())->resumeWithTan(
                $pending['flow'], $pending['stage'], $pending['context'],
                $pending['persist'], $pending['serialized'], trim($this->tan), $pending['pin'] ?? null,
            );
            $this->handleResult($result);
        } catch (FinTsTanRequiredException $e) {
            $this->requestTan($e);
        } catch (Throwable $e) {
            report($e);
            $this->step = 'idle';
            $this->notifyError($e);
        }
    }

    /** App-Freigabe („über das Handy") prüfen und Vorgang fortsetzen. */
    public function checkApproval(): void
    {
        $pending = session(self::SESSION_KEY);
        if (! $pending) {
            $this->step = 'idle';

            return;
        }

        try {
            $result = (new FinTsService())->checkDecoupled(
                $pending['flow'], $pending['stage'], $pending['context'],
                $pending['persist'], $pending['serialized'], $pending['pin'] ?? null,
            );
            $this->handleResult($result);
        } catch (FinTsTanRequiredException $e) {
            // Noch nicht freigegeben – Zustand aktualisieren und Hinweis geben.
            $this->requestTan($e);
            Notification::make()->title('Noch nicht freigegeben')
                ->body('Bitte die Freigabe in der Banking-App abschließen und erneut prüfen.')
                ->warning()->send();
        } catch (Throwable $e) {
            report($e);
            $this->step = 'idle';
            $this->notifyError($e);
        }
    }

    /** @param array<string, mixed> $result */
    private function handleResult(array $result): void
    {
        session()->forget(self::SESSION_KEY);
        $this->reset(['tan', 'tanChallenge', 'tanDecoupled']);

        if (($result['type'] ?? null) === 'accounts') {
            $this->showAccounts($result['accounts']);
        } else {
            $this->step = 'idle';
            $this->notifyImport($result['log']);
        }
    }

    /** Ausgewählte Konten als Bankkonten speichern. */
    public function saveAccounts(): void
    {
        $connection = FintsConnection::find($this->connectionId);
        if (! $connection || empty($this->selected)) {
            $this->notifyError('Bitte mindestens ein Konto auswählen.');

            return;
        }

        $byIban = collect($this->discovered)->keyBy('iban');
        $count = 0;

        foreach ($this->selected as $iban) {
            $info = $byIban->get($iban);
            if (! $info) {
                continue;
            }

            BankAccount::updateOrCreate(
                ['iban' => $iban],
                [
                    'fints_connection_id' => $connection->id,
                    'label' => $info['label'] ?: $iban,
                    'bic' => $info['bic'] ?? null,
                    'account_number' => $info['account_number'] ?? null,
                    'bank_code' => $info['bank_code'] ?? $connection->bank_code,
                    'fints_enabled' => true,
                    'active' => true,
                ]
            );
            $count++;
        }

        $this->reset(['step', 'discovered', 'selected']);
        Notification::make()->title("{$count} Konto/Konten gespeichert")->success()->send();
    }

    // ---- intern -----------------------------------------------------------

    /** @param array<int, array<string, mixed>> $accounts */
    private function showAccounts(array $accounts): void
    {
        $this->discovered = $accounts;
        $this->selected = collect($accounts)->pluck('iban')->filter()->values()->all();
        $this->step = 'accounts';

        Notification::make()->title(count($accounts) . ' Konto/Konten gefunden')->success()->send();
    }

    private function requestTan(FinTsTanRequiredException $e): void
    {
        session()->put(self::SESSION_KEY, [
            'flow' => $e->flow,
            'stage' => $e->stage,
            'context' => $e->context,
            'persist' => $e->persist,
            'serialized' => $e->serializedAction,
            'pin' => $this->pin ?: null,
        ]);

        $this->tan = '';
        $this->tanChallenge = $e->challenge;
        $this->tanDecoupled = $e->decoupled;
        $this->step = 'tan';
    }

    private function notifyImport($log): void
    {
        Notification::make()
            ->title('Umsätze abgerufen')
            ->body("{$log->new_count} neu, {$log->duplicate_count} Dubletten, {$log->error_count} Fehler.")
            ->success()
            ->send();
    }

    private function notifyError(\Throwable|string $error): void
    {
        Notification::make()->title('FinTS-Fehler')
            ->body(FinTsErrorTranslator::translate($error))
            ->danger()->send();
    }
}
