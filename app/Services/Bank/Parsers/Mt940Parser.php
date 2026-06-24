<?php

namespace App\Services\Bank\Parsers;

/**
 * Parser für MT940-Kontoauszüge (SWIFT, Modul 1).
 *
 * Verarbeitet die Umsatzzeilen :61: (Datum, Soll/Haben, Betrag) zusammen mit
 * den Mehrzweckfeldern :86: (?00 Buchungstext, ?20–?29 Verwendungszweck,
 * ?30 BIC, ?31 IBAN, ?32/?33 Name der Gegenseite).
 */
class Mt940Parser
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $content): array
    {
        $fields = $this->tokenizeFields($content);

        $transactions = [];
        $current = null;

        foreach ($fields as [$tag, $value]) {
            if ($tag === '61') {
                if ($current !== null) {
                    $transactions[] = $current;
                }
                $current = $this->parseLine($value);
            } elseif ($tag === '86' && $current !== null) {
                $current = array_merge($current, $this->parseInfo($value));
            }
        }

        if ($current !== null) {
            $transactions[] = $current;
        }

        return $transactions;
    }

    /**
     * Zerlegt den Text in [Tag, Wert]-Paare. Fortsetzungszeilen (ohne :TAG:)
     * werden an den vorherigen Wert angehängt.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function tokenizeFields(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $fields = [];
        $i = -1;

        foreach ($lines as $line) {
            if (preg_match('/^:(\d{2}[A-Z]?):(.*)$/', $line, $m)) {
                $fields[] = [$m[1], $m[2]];
                $i++;
            } elseif ($i >= 0 && $line !== '-') {
                $fields[$i][1] .= "\n" . $line;
            }
        }

        return $fields;
    }

    /**
     * Parst eine :61:-Umsatzzeile.
     *
     * @return array<string, mixed>
     */
    private function parseLine(string $value): array
    {
        $value = str_replace("\n", '', $value);
        preg_match(
            '/^(?<vdate>\d{6})(?<edate>\d{4})?(?<mark>R?[CD])(?<amount>[\d,]+)(?<code>[A-Z][A-Z0-9]{3})?(?<ref>.*)$/',
            $value,
            $m
        );

        $valueDate = $this->parseDate($m['vdate'] ?? null);
        $bookingDate = isset($m['edate']) && $m['edate'] !== ''
            ? $this->parseEntryDate($m['edate'], $m['vdate'] ?? null)
            : $valueDate;

        $amount = $this->parseAmount($m['amount'] ?? '0');
        $mark = $m['mark'] ?? 'C';
        // C=Haben(+), D=Soll(-); ein vorangestelltes R (Storno) kehrt das Vorzeichen um.
        $sign = str_contains($mark, 'D') ? -1 : 1;
        if (str_starts_with($mark, 'R')) {
            $sign *= -1;
        }

        return [
            'booking_date' => $bookingDate,
            'value_date' => $valueDate,
            'amount' => $sign * $amount,
            'bank_reference' => trim(explode('//', $m['ref'] ?? '')[1] ?? '') ?: null,
        ];
    }

    /**
     * Parst eine :86:-Infozeile mit ?NN-Subfeldern.
     *
     * @return array<string, mixed>
     */
    private function parseInfo(string $value): array
    {
        $value = str_replace("\n", '', $value);
        // Subfelder ?NN extrahieren
        preg_match_all('/\?(\d{2})([^?]*)/', $value, $matches, PREG_SET_ORDER);

        $sub = [];
        foreach ($matches as $match) {
            $sub[(int) $match[1]] = trim($match[2]);
        }

        // Verwendungszweck = ?20..?29 zusammengesetzt
        $purpose = '';
        for ($n = 20; $n <= 29; $n++) {
            if (isset($sub[$n])) {
                $purpose .= ' ' . $sub[$n];
            }
        }

        $name = trim(($sub[32] ?? '') . ' ' . ($sub[33] ?? ''));

        return array_filter([
            'booking_text' => $sub[0] ?? null,
            'purpose' => trim($purpose) ?: null,
            'counterparty_bic' => $sub[30] ?? null,
            'counterparty_iban' => $sub[31] ?? null,
            'counterparty' => $name ?: null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function parseDate(?string $yymmdd): ?string
    {
        if (! $yymmdd || strlen($yymmdd) !== 6) {
            return null;
        }
        $year = 2000 + (int) substr($yymmdd, 0, 2);
        $month = substr($yymmdd, 2, 2);
        $day = substr($yymmdd, 4, 2);

        return sprintf('%04d-%s-%s', $year, $month, $day);
    }

    private function parseEntryDate(string $mmdd, ?string $valueDate): ?string
    {
        // Jahr aus dem Valutadatum übernehmen (Buchungsdatum hat nur MMTT).
        $year = $valueDate ? 2000 + (int) substr($valueDate, 0, 2) : (int) date('Y');

        return sprintf('%04d-%s-%s', $year, substr($mmdd, 0, 2), substr($mmdd, 2, 2));
    }

    private function parseAmount(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
