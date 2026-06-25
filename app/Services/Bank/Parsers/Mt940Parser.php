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
        // Soll/Haben-Kennung: C, D, RC, RD (Storno) sowie CR/DR (R hinten, manche Banken).
        preg_match(
            '/^(?<vdate>\d{6})(?<edate>\d{4})?(?<mark>R?[CD]R?)(?<amount>[\d,]+)(?<code>[A-Z][A-Z0-9]{3})?(?<ref>.*)$/',
            $value,
            $m
        );

        $valueDate = $this->parseDate($m['vdate'] ?? null);
        $bookingDate = isset($m['edate']) && $m['edate'] !== ''
            ? $this->parseEntryDate($m['edate'], $valueDate)
            : $valueDate;

        $amount = $this->parseAmount($m['amount'] ?? '0');
        $mark = strtoupper($m['mark'] ?? 'C');
        // C/CR = Haben (+), D/DR = Soll (−); RC/RD = Storno (kehrt das Vorzeichen um).
        $sign = match ($mark) {
            'C', 'CR' => 1,
            'D', 'DR' => -1,
            'RC' => -1,
            'RD' => 1,
            default => str_contains($mark, 'D') ? -1 : 1,
        };

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
        // Buchungsdatum hat nur MMTT -> Jahr aus dem Valutadatum (Y-m-d) ableiten.
        $year = $valueDate ? (int) substr($valueDate, 0, 4) : (int) date('Y');
        $month = (int) substr($mmdd, 0, 2);
        $day = (int) substr($mmdd, 2, 2);

        if (! checkdate($month, $day, $year)) {
            return $valueDate;
        }

        $candidate = sprintf('%04d-%02d-%02d', $year, $month, $day);

        // Jahreswechsel ausgleichen: liegt das Buchungsdatum mit Valuta-Jahr weit
        // vom Valutadatum entfernt, gehört es ins Vor-/Folgejahr
        // (z. B. Valuta 31.12.2025, Buchung 02.01. -> 02.01.2026).
        if ($valueDate) {
            $diff = (strtotime($candidate) - strtotime($valueDate)) / 86400;
            if ($diff < -300) {
                $candidate = sprintf('%04d-%02d-%02d', $year + 1, $month, $day);
            } elseif ($diff > 300) {
                $candidate = sprintf('%04d-%02d-%02d', $year - 1, $month, $day);
            }
        }

        return $candidate;
    }

    private function parseAmount(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }
}
