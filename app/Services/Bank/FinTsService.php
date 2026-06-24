<?php

namespace App\Services\Bank;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\FintsConnection;
use App\Models\ImportLog;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\FinTs;
use Fhp\Model\StatementOfAccount\Transaction;
use Fhp\Options\Credentials;
use Fhp\Options\FinTsOptions;
use Illuminate\Support\Carbon;

/**
 * FinTS-/HBCI-Direktabruf (Modul 1) über nemiah/php-fints.
 *
 * Baut aus einem FintsConnection-Datensatz die Verbindung auf, ruft die Umsätze
 * eines Kontos für einen Zeitraum ab und übergibt sie an den BankImportService
 * (inkl. Dublettenprüfung). Verlangt die Bank eine TAN, wird eine
 * {@see FinTsTanRequiredException} geworfen.
 *
 * Hinweis: Live-Abruf benötigt echte Bankzugangsdaten und kann daher nicht
 * automatisiert getestet werden. Der TAN-Fortsetzungs-Flow ist als
 * Ausbaustufe vorgesehen.
 */
class FinTsService
{
    public function __construct(
        private readonly BankImportService $importer = new BankImportService(),
    ) {}

    public function fetchAccount(BankAccount $account, ?Carbon $from = null, ?Carbon $to = null): ImportLog
    {
        $connection = $account->fintsConnection;
        if (! $connection) {
            throw new \RuntimeException('Für dieses Konto ist kein FinTS-Zugang hinterlegt.');
        }

        $fints = $this->makeClient($connection);
        $this->selectTanMode($fints, $connection);

        $login = $fints->login();
        $this->guardTan($fints, $login);

        // SEPA-Konten ermitteln und passendes Konto per IBAN wählen
        $getAccounts = GetSEPAAccounts::create();
        $fints->execute($getAccounts);
        $this->guardTan($fints, $getAccounts);

        $sepaAccount = $this->findAccount($getAccounts->getAccounts(), $account);

        // Umsätze abrufen (Standard: letzte 90 Tage)
        $from ??= Carbon::now()->subDays(90);
        $to ??= Carbon::now();

        $statement = GetStatementOfAccount::create($sepaAccount, $from->toDateTime(), $to->toDateTime());
        $fints->execute($statement);
        $this->guardTan($fints, $statement);

        $rows = $this->mapTransactions($statement->getStatement());

        $connection->forceFill([
            'last_fetched_at' => now(),
            'last_message' => sprintf('%d Umsätze abgerufen.', count($rows)),
        ])->saveQuietly();

        return $this->importer->import($account, $rows, ImportSource::Fints);
    }

    private function makeClient(FintsConnection $connection): FinTs
    {
        $options = new FinTsOptions();
        $options->url = $connection->fints_url;
        $options->bankCode = $connection->bank_code;
        $options->productName = $connection->product_id ?: config('pendelordner.fints.product_id', 'PENDELORDNER');
        $options->productVersion = $connection->product_version ?: '1.0';

        $credentials = Credentials::create($connection->username, (string) $connection->pin);

        return FinTs::new($options, $credentials);
    }

    private function selectTanMode(FinTs $fints, FintsConnection $connection): void
    {
        if ($connection->tan_method) {
            $fints->selectTanMode((int) $connection->tan_method, $connection->tan_medium ?: null);
        }
    }

    /**
     * Prüft, ob die Aktion eine TAN verlangt, und bricht in dem Fall mit einer
     * fortsetzbaren Exception ab.
     */
    private function guardTan(FinTs $fints, object $action): void
    {
        if (method_exists($action, 'needsTan') && $action->needsTan()) {
            $tanRequest = $action->getTanRequest();
            throw new FinTsTanRequiredException(
                $fints->persist(),
                $tanRequest?->getChallenge() ?? 'TAN erforderlich',
            );
        }
    }

    /**
     * @param  array<int, \Fhp\Model\SEPAAccount>  $accounts
     */
    private function findAccount(array $accounts, BankAccount $account): \Fhp\Model\SEPAAccount
    {
        foreach ($accounts as $sepa) {
            if ($account->iban && method_exists($sepa, 'getIban')
                && $this->normalizeIban($sepa->getIban()) === $this->normalizeIban($account->iban)) {
                return $sepa;
            }
        }

        if (! empty($accounts)) {
            return $accounts[0];
        }

        throw new \RuntimeException('Kein passendes SEPA-Konto beim FinTS-Zugang gefunden.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapTransactions(\Fhp\Model\StatementOfAccount\StatementOfAccount $soa): array
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
