<?php

namespace App\Services\Bank\Parsers;

/**
 * Parser für CSV-Kontoauszüge deutscher Banken (Modul 1).
 *
 * Header-basiert mit Alias-Erkennung gängiger Spaltennamen (VR-Bank/Sparkasse
 * CSV-CAMT-Export u. a.). Erkennt Trennzeichen (; oder ,) automatisch und
 * verarbeitet das deutsche Dezimalkomma sowie Soll/Haben-Vorzeichen.
 */
class CsvBankParser
{
    /** Alias-Liste je Zielfeld (kleingeschrieben, ohne Sonderzeichen-Normalisierung). */
    private const ALIASES = [
        'booking_date' => ['buchungstag', 'buchungsdatum', 'datum'],
        'value_date' => ['valutadatum', 'valuta', 'wertstellung', 'wert'],
        'counterparty' => ['name zahlungsbeteiligter', 'beguenstigter/zahlungspflichtiger', 'empfaenger', 'auftraggeber/empfaenger', 'beguenstigter', 'zahlungsempfaenger', 'name'],
        'counterparty_iban' => ['iban zahlungsbeteiligter', 'kontonummer/iban', 'iban', 'kontonummer'],
        'counterparty_bic' => ['bic (swift-code) zahlungsbeteiligter', 'bic', 'bic (swift-code)'],
        'purpose' => ['verwendungszweck', 'vwz'],
        'amount' => ['betrag', 'umsatz', 'betrag (eur)'],
        'currency' => ['waehrung', 'währung'],
        'booking_text' => ['buchungstext', 'umsatzart'],
    ];

    /** Spalten, die das eigene Konto (Auftragskonto) beschreiben. */
    private const OWNER_ALIASES = [
        'name' => ['bezeichnung auftragskonto', 'bezeichnung konto', 'kontobezeichnung'],
        'iban' => ['iban auftragskonto', 'auftragskonto iban', 'iban'],
        'bic' => ['bic auftragskonto', 'auftragskonto bic'],
        'bank_name' => ['bankname auftragskonto', 'bank auftragskonto'],
        'account_number' => ['auftragskonto', 'kontonummer auftragskonto'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $content = $this->stripBom($content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (count($lines) < 2) {
            return [];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header = $this->splitLine(array_shift($lines), $delimiter);
        $map = $this->mapHeader($header);

        if (! isset($map['booking_date'], $map['amount'])) {
            throw new \RuntimeException('CSV-Header enthält weder Buchungsdatum noch Betrag (erkannte Spalten: ' . implode(', ', $header) . ').');
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = $this->splitLine($line, $delimiter);

            $get = fn (string $key) => isset($map[$key], $cols[$map[$key]]) ? $cols[$map[$key]] : null;

            $rows[] = [
                'booking_date' => $get('booking_date'),
                'value_date' => $get('value_date'),
                'counterparty' => $get('counterparty'),
                'counterparty_iban' => $get('counterparty_iban'),
                'counterparty_bic' => $get('counterparty_bic'),
                'purpose' => $get('purpose'),
                'amount' => $this->parseAmount($get('amount')),
                'currency' => $get('currency'),
                'booking_text' => $get('booking_text'),
            ];
        }

        return $rows;
    }

    /**
     * Liest die Daten des eigenen Kontos (Auftragskonto) aus den Kopf-/Datenspalten,
     * sofern die CSV sie enthält (z. B. VR-Bank-/Sparkassen-Export). Liefert die in
     * allen Zeilen konstanten Werte, damit nicht versehentlich Gegenkonten greifen.
     *
     * @return array{name: ?string, iban: ?string, bic: ?string, bank_name: ?string, account_number: ?string}|null
     */
    public function detectOwnerAccount(string $content): ?array
    {
        $content = $this->stripBom($content);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (count($lines) < 2) {
            return null;
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header = array_map(fn ($h) => $this->normalizeHeader($h), $this->splitLine($lines[0], $delimiter));

        $idx = [];
        foreach (self::OWNER_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $pos = array_search($alias, $header, true);
                if ($pos !== false) {
                    $idx[$field] = $pos;
                    break;
                }
            }
        }

        if (! isset($idx['iban']) && ! isset($idx['account_number'])) {
            return null;
        }

        // Wert je Feld aus der ersten nicht-leeren Datenzeile.
        $owner = ['name' => null, 'iban' => null, 'bic' => null, 'bank_name' => null, 'account_number' => null];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = $this->splitLine($line, $delimiter);
            foreach ($idx as $field => $pos) {
                $owner[$field] = isset($cols[$pos]) && $cols[$pos] !== '' ? $cols[$pos] : null;
            }
            break;
        }

        if (! $owner['iban'] && ! $owner['account_number']) {
            return null;
        }

        return $owner;
    }

    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') >= substr_count($headerLine, ',') ? ';' : ',';
    }

    /**
     * @return array<int, string>
     */
    private function splitLine(string $line, string $delimiter): array
    {
        return array_map(fn ($v) => trim($v, " \t\"'"), str_getcsv($line, $delimiter, '"', '\\'));
    }

    /**
     * @param  array<int, string>  $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(fn ($h) => $this->normalizeHeader($h), $header);
        $map = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $normalized, true);
                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h));

        return strtr($h, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    }

    private function parseAmount(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }
        $value = trim($value);
        // Deutsches Format: Tausenderpunkt entfernen, Komma -> Punkt
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        $value = preg_replace('/[^0-9.\-]/', '', $value);

        return (float) $value;
    }

    private function stripBom(string $content): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $content);
    }
}
