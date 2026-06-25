<?php

namespace App\Services\Bank;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\FintsConnection;
use App\Models\ImportLog;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Model\StatementOfAccount\StatementOfAccount;
use Fhp\Model\StatementOfAccount\Transaction;
use Fhp\Options\Credentials;
use Fhp\Options\FinTsOptions;
use Illuminate\Support\Carbon;

/**
 * FinTS-/HBCI-Anbindung (Modul 1) über nemiah/php-fints.
 *
 * Unterstützt zwei Abläufe:
 *  - discoverAccounts(): listet die Konten eines Zugangs (zum Auswählen/Speichern)
 *  - fetchAccount():     ruft die Umsätze eines Kontos ab und importiert sie
 *
 * Verlangt die Bank eine TAN (typisch bei VR-Banken), wird eine
 * {@see FinTsTanRequiredException} mit dem vollständigen Fortsetzungszustand
 * geworfen; nach TAN-Eingabe setzt {@see resumeWithTan()} den Vorgang fort.
 */
class FinTsService
{
    public function __construct(
        private readonly BankImportService $importer = new BankImportService(),
    ) {}

    // ===================================================================
    //  Einstiegspunkte
    // ===================================================================

    /**
     * Konten eines FinTS-Zugangs ermitteln.
     *
     * @return array<int, array<string, mixed>>
     */
    public function discoverAccounts(FintsConnection $connection, ?string $pin = null): array
    {
        $fints = $this->makeClient($connection, null, $pin);
        $this->selectTanMode($fints, $connection);

        $ctx = ['connection_id' => $connection->id];

        $login = $fints->login();
        if ($login->needsTan()) {
            throw $this->tan($fints, $login, 'discover', 'login', $ctx);
        }

        return $this->mapAccounts($this->stepAccounts($fints, 'discover', $ctx));
    }

    /**
     * Umsätze eines Kontos abrufen und importieren.
     */
    public function fetchAccount(BankAccount $account, ?Carbon $from = null, ?Carbon $to = null, ?string $pin = null): ImportLog
    {
        $connection = $account->fintsConnection;
        if (! $connection) {
            throw new \RuntimeException('Für dieses Konto ist kein FinTS-Zugang hinterlegt.');
        }

        $from ??= Carbon::now()->subDays((int) config('pendelordner.fints.default_days', 90));
        $to ??= Carbon::now();

        $ctx = [
            'connection_id' => $connection->id,
            'account_id' => $account->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        $fints = $this->makeClient($connection, null, $pin);
        $this->selectTanMode($fints, $connection);

        $login = $fints->login();
        if ($login->needsTan()) {
            throw $this->tan($fints, $login, 'fetch', 'login', $ctx);
        }

        $sepaAccounts = $this->stepAccounts($fints, 'fetch', $ctx);

        return $this->stepStatement($fints, $account, $sepaAccounts, $from, $to, $ctx);
    }

    /**
     * Setzt einen durch TAN unterbrochenen Vorgang fort.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>  ['type' => 'accounts', 'accounts' => [...]] | ['type' => 'log', 'log' => ImportLog]
     */
    public function resumeWithTan(string $flow, string $stage, array $context, string $persist, string $serializedAction, string $tan, ?string $pin = null): array
    {
        $connection = FintsConnection::findOrFail($context['connection_id']);
        $fints = $this->makeClient($connection, $persist, $pin);
        $this->selectTanMode($fints, $connection);

        /** @var BaseAction $action */
        $action = unserialize($serializedAction);
        $fints->submitTan($action, $tan);

        return $this->continueAfterAuth($fints, $action, $flow, $stage, $context);
    }

    /**
     * Prüft, ob eine entkoppelte Freigabe (App-Freigabe „über das Handy")
     * erfolgt ist, und setzt den Vorgang fort. Ist noch nicht freigegeben, wird
     * erneut eine (Decoupled-)TAN-Exception geworfen.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function checkDecoupled(string $flow, string $stage, array $context, string $persist, string $serializedAction, ?string $pin = null): array
    {
        $connection = FintsConnection::findOrFail($context['connection_id']);
        $fints = $this->makeClient($connection, $persist, $pin);
        $this->selectTanMode($fints, $connection);

        /** @var BaseAction $action */
        $action = unserialize($serializedAction);

        if (! $fints->checkDecoupledSubmission($action)) {
            // Noch nicht freigegeben – Zustand aktualisieren und erneut melden.
            throw $this->tan($fints, $action, $flow, $stage, $context);
        }

        return $this->continueAfterAuth($fints, $action, $flow, $stage, $context);
    }

    /**
     * Gemeinsame Fortsetzungslogik nach erfolgreicher Authentifizierung
     * (TAN-Eingabe oder App-Freigabe).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function continueAfterAuth(FinTs $fints, BaseAction $action, string $flow, string $stage, array $context): array
    {
        // Manche Verfahren verlangen eine weitere Bestätigung.
        if ($action->needsTan()) {
            throw $this->tan($fints, $action, $flow, $stage, $context);
        }

        if ($flow === 'discover') {
            $sepa = $stage === 'login'
                ? $this->stepAccounts($fints, 'discover', $context)
                : $action->getAccounts();

            return ['type' => 'accounts', 'accounts' => $this->mapAccounts($sepa)];
        }

        // flow === 'fetch'
        $account = BankAccount::findOrFail($context['account_id']);
        $from = Carbon::parse($context['from']);
        $to = Carbon::parse($context['to']);

        if ($stage === 'statement') {
            return ['type' => 'log', 'log' => $this->importStatement($account, $action->getStatement())];
        }

        $sepa = $stage === 'login'
            ? $this->stepAccounts($fints, 'fetch', $context)
            : $action->getAccounts();

        return ['type' => 'log', 'log' => $this->stepStatement($fints, $account, $sepa, $from, $to, $context)];
    }

    // ===================================================================
    //  Einzelschritte
    // ===================================================================

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<int, SEPAAccount>
     */
    private function stepAccounts(FinTs $fints, string $flow, array $ctx): array
    {
        $get = GetSEPAAccounts::create();
        $fints->execute($get);

        if ($get->needsTan()) {
            throw $this->tan($fints, $get, $flow, 'accounts', $ctx);
        }

        return $get->getAccounts();
    }

    /**
     * @param  array<int, SEPAAccount>  $sepaAccounts
     * @param  array<string, mixed>  $ctx
     */
    private function stepStatement(FinTs $fints, BankAccount $account, array $sepaAccounts, Carbon $from, Carbon $to, array $ctx): ImportLog
    {
        $sepa = $this->findAccount($sepaAccounts, $account);

        $statement = GetStatementOfAccount::create($sepa, $from->toDateTime(), $to->toDateTime());
        $fints->execute($statement);

        if ($statement->needsTan()) {
            throw $this->tan($fints, $statement, 'fetch', 'statement', $ctx);
        }

        return $this->importStatement($account, $statement->getStatement());
    }

    private function importStatement(BankAccount $account, StatementOfAccount $soa): ImportLog
    {
        $rows = $this->mapTransactions($soa);

        $account->fintsConnection?->forceFill([
            'last_fetched_at' => now(),
            'last_message' => sprintf('%d Umsätze abgerufen.', count($rows)),
        ])->saveQuietly();

        return $this->importer->import($account, $rows, ImportSource::Fints);
    }

    // ===================================================================
    //  Hilfsfunktionen
    // ===================================================================

    private function makeClient(FintsConnection $connection, ?string $persist = null, ?string $pinOverride = null): FinTs
    {
        $options = new FinTsOptions();
        $options->url = $connection->fints_url;
        $options->bankCode = $connection->bank_code;
        // Produktname/-registrierung darf nie leer sein (Bibliothek verlangt ihn).
        $options->productName = $connection->product_id
            ?: (config('pendelordner.fints.product_id') ?: 'PENDELORDNER');
        $options->productVersion = $connection->product_version
            ?: (config('pendelordner.fints.product_version') ?: '1.0');

        // PIN: zur Laufzeit eingegebene PIN hat Vorrang vor der gespeicherten.
        $pin = $pinOverride !== null && $pinOverride !== '' ? $pinOverride : (string) $connection->pin;
        if ($pin === '') {
            throw new \RuntimeException('Die PIN fehlt – bitte PIN eingeben oder im FinTS-Zugang hinterlegen.');
        }

        $credentials = Credentials::create($connection->username, $pin);

        return FinTs::new($options, $credentials, $persist);
    }

    private function selectTanMode(FinTs $fints, FintsConnection $connection): void
    {
        if ($connection->tan_method) {
            $fints->selectTanMode((int) $connection->tan_method, $connection->tan_medium ?: null);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function tan(FinTs $fints, BaseAction $action, string $flow, string $stage, array $context): FinTsTanRequiredException
    {
        $decoupled = $fints->getSelectedTanMode()?->isDecoupled() ?? false;

        return new FinTsTanRequiredException(
            persist: $fints->persist(),
            serializedAction: serialize($action),
            flow: $flow,
            stage: $stage,
            context: $context,
            challenge: $action->getTanRequest()?->getChallenge()
                ?? ($decoupled ? 'Bitte in Ihrer Banking-App freigeben.' : 'Bitte TAN eingeben.'),
            decoupled: $decoupled,
        );
    }

    /**
     * @param  array<int, SEPAAccount>  $accounts
     */
    private function findAccount(array $accounts, BankAccount $account): SEPAAccount
    {
        foreach ($accounts as $sepa) {
            if ($account->iban && $this->normalizeIban($sepa->getIban()) === $this->normalizeIban($account->iban)) {
                return $sepa;
            }
        }

        if (! empty($accounts)) {
            return $accounts[0];
        }

        throw new \RuntimeException('Kein passendes SEPA-Konto beim FinTS-Zugang gefunden.');
    }

    /**
     * @param  array<int, SEPAAccount>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function mapAccounts(array $accounts): array
    {
        return array_map(fn (SEPAAccount $a) => [
            'iban' => $a->getIban(),
            'bic' => $a->getBic(),
            'account_number' => $a->getAccountNumber(),
            'bank_code' => $a->getBlz(),
            'label' => trim(($a->getIban() ?: $a->getAccountNumber() ?: 'Konto')),
        ], $accounts);
    }

    private function mapTransactions(StatementOfAccount $soa): array
    {
        $rows = [];

        foreach ($soa->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $t) {
                /** @var Transaction $t */
                $amount = $t->getAmount();
                if (stripos($t->getCreditDebit(), 'debit') !== false) {
                    $amount *= -1;
                }

                $rows[] = [
                    'booking_date' => $t->getBookingDate()?->format('Y-m-d'),
                    'value_date' => $t->getValutaDate()?->format('Y-m-d'),
                    'amount' => $amount,
                    'counterparty' => $t->getName() ?: null,
                    'counterparty_iban' => $t->getAccountNumber() ?: null,
                    'purpose' => $t->getMainDescription() ?: null,
                    'booking_text' => $t->getBookingText() ?: null,
                    'bank_reference' => $t->getEndToEndID() ?: null,
                ];
            }
        }

        return $rows;
    }

    private function normalizeIban(?string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $iban));
    }
}
