<?php

namespace App\Services\Bank;

use Throwable;

/**
 * Übersetzt technische FinTS-/Bibliotheksmeldungen (meist englisch) in
 * verständliche deutsche Hinweise.
 */
class FinTsErrorTranslator
{
    /** Teilstring (kleingeschrieben) => deutsche Meldung. */
    private const MAP = [
        'pin cannot be empty' => 'Die PIN fehlt – bitte PIN eingeben.',
        'pin must not be empty' => 'Die PIN fehlt – bitte PIN eingeben.',
        'username cannot be empty' => 'Die Benutzerkennung fehlt.',
        'benutzerkennung' => 'Die Benutzerkennung fehlt oder ist ungültig.',
        'could not resolve host' => 'Verbindung zur Bank fehlgeschlagen – bitte FinTS-URL und Internetverbindung prüfen.',
        'could not connect' => 'Verbindung zur Bank fehlgeschlagen – bitte FinTS-URL und Internetverbindung prüfen.',
        'connection timed out' => 'Zeitüberschreitung bei der Verbindung zur Bank.',
        'curl error' => 'Verbindungsfehler zur Bank (Netzwerk/URL).',
        'ssl certificate' => 'SSL-/Zertifikatsfehler bei der Verbindung zur Bank.',
        'certificate' => 'Zertifikatsfehler bei der Verbindung zur Bank.',
        'selecttanmode' => 'Es wurde kein TAN-Verfahren gewählt.',
        'does not support psd2' => 'Die Bank unterstützt dieses (PSD2-)Verfahren nicht.',
        'not awaiting decoupled' => 'Für diesen Vorgang ist keine App-Freigabe offen.',
        'invalid pin' => 'Anmeldung fehlgeschlagen – PIN falsch.',
        'wrong pin' => 'Anmeldung fehlgeschlagen – PIN falsch.',
        'pin is locked' => 'Die PIN ist gesperrt – bitte bei der Bank entsperren lassen.',
        'locked' => 'Der Zugang ist gesperrt – bitte bei der Bank prüfen.',
        'account is locked' => 'Der Zugang ist gesperrt – bitte bei der Bank prüfen.',
        'unexpected response' => 'Unerwartete Antwort der Bank.',
        'no accounts' => 'Es wurden keine Konten gefunden.',
        'product name required' => 'FinTS-Produktname/Registrierung fehlt (Feld „Produkt-ID" im Zugang oder Standard wird genutzt).',
        'product registration' => 'FinTS-Produktregistrierung erforderlich.',
    ];

    public static function translate(Throwable|string $error): string
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $haystack = mb_strtolower($message);

        foreach (self::MAP as $needle => $german) {
            if (str_contains($haystack, $needle)) {
                return $german;
            }
        }

        // Bereits deutschsprachige Eigenmeldungen unverändert zurückgeben.
        return $message;
    }
}
