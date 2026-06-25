<?php

/**
 * FinTS-Testprogramm (nemiah/php-fints v4) – eigenständiges CLI-Skript.
 *
 * Aufruf:   php fints_test.php
 *
 * Ablauf:
 *   1. Verbindung aufbauen, TAN-Verfahren wählen
 *   2. Login (mit TAN-/App-Freigabe, falls die Bank das verlangt)
 *   3. SEPA-Konten auflisten
 *   4. Umsätze der letzten 14 Tage je Konto ausgeben
 *
 * Hinweis: VR-Banken (Atruvia/Fiducia) verlangen praktisch immer eine TAN
 * bzum Login. Das Skript fragt sie interaktiv ab bzw. wartet bei App-Freigabe.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Options\Credentials;
use Fhp\Options\FinTsOptions;

// ====================================================================
//  KONFIGURATION  – hier deine Zugangsdaten eintragen
// ====================================================================
$BANK_URL   = 'https://fints2.atruvia.de/cgi-bin/hbciservlet'; // VR Bank Fulda (Atruvia)
$BANK_CODE  = '53060180';            // BLZ
$USERNAME   = 'DEINE_VR_KENNUNG';    // VR-NetKey / Anmeldename
$PIN        = 'DEINE_PIN';           // Online-Banking-PIN

$PRODUCT_NAME    = 'PENDELORDNER';   // ggf. eigene FinTS-Registrierungs-ID
$PRODUCT_VERSION = '1.0';

// TAN-Verfahren: null => verfügbare Verfahren werden aufgelistet und das Skript
// stoppt, damit du die passende Nummer hier einträgst (z. B. 942 = SecureGo).
$TAN_MODE   = null;                  // int|null
$TAN_MEDIUM = null;                  // string|null (Name des TAN-Mediums, falls nötig)

$FROM = new DateTime('-14 days');
$TO   = new DateTime();
// ====================================================================

// kleine Konsolen-Eingabe
function prompt(string $label): string
{
    echo $label;
    return trim((string) fgets(STDIN));
}

function line(string $s = ''): void
{
    echo $s . PHP_EOL;
}

try {
    $options = new FinTsOptions();
    $options->url = $BANK_URL;
    $options->bankCode = $BANK_CODE;
    $options->productName = $PRODUCT_NAME;
    $options->productVersion = $PRODUCT_VERSION;
    $options->validate();

    $credentials = Credentials::create($USERNAME, $PIN);

    $fints = FinTs::new($options, $credentials);

    // ----------------------------------------------------------------
    // 1. TAN-Verfahren wählen (bzw. verfügbare auflisten)
    // ----------------------------------------------------------------
    if ($TAN_MODE === null) {
        line('Verfügbare TAN-Verfahren dieser Bank/Kennung:');
        line('-------------------------------------------------');
        foreach ($fints->getTanModes() as $mode) {
            line(sprintf(
                '  %-5d %s%s',
                $mode->getId(),
                $mode->getName(),
                $mode->isDecoupled() ? '  [App-Freigabe / decoupled]' : ''
            ));
        }
        line('-------------------------------------------------');
        line('Bitte $TAN_MODE oben im Skript auf eine dieser Nummern setzen');
        line('und das Skript erneut starten.');
        exit(0);
    }

    $fints->selectTanMode($TAN_MODE, $TAN_MEDIUM);

    // ----------------------------------------------------------------
    // 2. Login (mit TAN-Handling)
    // ----------------------------------------------------------------
    line('Melde an ...');
    $login = $fints->login();
    handleTan($fints, $login);
    line('Login erfolgreich.');
    line();

    // ----------------------------------------------------------------
    // 3. Konten abrufen
    // ----------------------------------------------------------------
    $getAccounts = GetSEPAAccounts::create();
    $fints->execute($getAccounts);
    handleTan($fints, $getAccounts);

    /** @var SEPAAccount[] $accounts */
    $accounts = $getAccounts->getAccounts();
    line(sprintf('%d Konto/Konten gefunden:', count($accounts)));
    foreach ($accounts as $a) {
        line('  - ' . ($a->getIban() ?: $a->getAccountNumber()) . '  (BIC ' . $a->getBic() . ')');
    }
    line();

    // ----------------------------------------------------------------
    // 4. Umsätze je Konto
    // ----------------------------------------------------------------
    foreach ($accounts as $account) {
        line('================================================');
        line('Konto: ' . ($account->getIban() ?: $account->getAccountNumber()));
        line('Zeitraum: ' . $FROM->format('d.m.Y') . ' – ' . $TO->format('d.m.Y'));
        line('================================================');

        $statementAction = GetStatementOfAccount::create($account, $FROM, $TO);
        $fints->execute($statementAction);
        handleTan($fints, $statementAction);

        $soa = $statementAction->getStatement();
        $count = 0;
        foreach ($soa->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $t) {
                $count++;
                $sign = stripos((string) $t->getCreditDebit(), 'debit') !== false ? '-' : '+';
                line('Datum:    ' . ($t->getBookingDate()?->format('d.m.Y') ?? '—')
                    . '  (Valuta ' . ($t->getValutaDate()?->format('d.m.Y') ?? '—') . ')');
                line('Betrag:   ' . $sign . number_format((float) $t->getAmount(), 2, ',', '.') . ' EUR');
                line('Name:     ' . ($t->getName() ?: '—'));
                line('Zweck:    ' . ($t->getMainDescription() ?: '—'));
                line('BuchText: ' . ($t->getBookingText() ?: '—'));
                line('----------------------------------------');
            }
        }
        line($count . ' Umsätze.' . PHP_EOL);
    }

    line('Fertig.');
} catch (Throwable $e) {
    line();
    line('FEHLER: ' . $e->getMessage());
    line('Typ:    ' . get_class($e));
    exit(1);
}

/**
 * Behandelt eine ggf. von der Bank geforderte TAN bzw. App-Freigabe für die
 * übergebene Aktion. Bei „decoupled" (App-Freigabe) wird gepollt.
 */
function handleTan(FinTs $fints, BaseAction $action): void
{
    if (! $action->needsTan()) {
        return;
    }

    $tanRequest = $action->getTanRequest();
    $isDecoupled = $fints->getSelectedTanMode()?->isDecoupled() ?? false;

    line();
    line('>>> Die Bank verlangt eine Freigabe:');
    if ($tanRequest?->getChallenge()) {
        line('    ' . strip_tags($tanRequest->getChallenge()));
    }
    if ($tanRequest?->getTanMediumName()) {
        line('    TAN-Medium: ' . $tanRequest->getTanMediumName());
    }

    if ($isDecoupled) {
        line('    Bitte in deiner Banking-App freigeben ...');
        do {
            sleep(5);
            line('    ... prüfe Freigabe ...');
        } while (! $fints->checkDecoupledSubmission($action));
        line('    Freigabe erkannt.');
    } else {
        $tan = prompt('    TAN eingeben: ');
        $fints->submitTan($action, $tan);
    }
    line();
}
