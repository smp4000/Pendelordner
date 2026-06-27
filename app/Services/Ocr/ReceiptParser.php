<?php

namespace App\Services\Ocr;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Heuristische Extraktion von Rechnungsdaten aus OCR-Text (Modul 3):
 * Rechnungsnummer, Rechnungsdatum, Leistungsdatum, Bruttobetrag, Steuersatz,
 * Steuerbetrag und IBAN. Bewusst tolerant – die erkannten Werte werden im
 * Formular zur Prüfung angezeigt.
 *
 * @return array<string, mixed>
 */
class ReceiptParser
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $text): array
    {
        // Manche PDF-Reader streuen Pseudo-Marker "<>" zwischen Buchstaben ein
        // (z. B. "Gesamtbetr<>ag"); entfernen, sonst greifen Schlüsselwörter nicht.
        $text = str_replace('<>', '', $text);

        return [
            'invoice_number' => $this->invoiceNumber($text),
            'customer_number' => $this->customerNumber($text),
            'invoice_date' => $this->date($text, ['rechnungsdatum', 'rechnung vom', 'datum', 'belegdatum']),
            'service_date' => $this->date($text, ['leistungsdatum', 'lieferdatum', 'leistungszeitraum']),
            'iban' => $this->iban($text),
            'tax_rate' => $this->taxRate($text),
            'gross_amount' => $this->grossAmount($text),
            'tax_amount' => $this->taxAmount($text),
        ];
    }

    /** Kundennummer beim Lieferanten, z. B. "Kundennummer  A8319" oder "Kd.-Nr.: 12345". */
    private function customerNumber(string $text): ?string
    {
        $patterns = [
            '/kunden(?:nummer|nr)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{1,})/i',
            '/kd\.?[\s\-]*nr\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{1,})/i',
            '/debitor(?:en)?[\s\-]*(?:nr|nummer)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{1,})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    private function invoiceNumber(string $text): ?string
    {
        $patterns = [
            '/rechnung(?:s)?[\s\-]*(?:nr|nummer)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,})/i',
            '/(?:beleg|rg)[\s\-]*(?:nr|nummer)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,})/i',
            '/invoice\s*(?:no|number)\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\-\/]{2,})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function date(string $text, array $keywords): ?string
    {
        $datePattern = '(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})';

        foreach ($keywords as $keyword) {
            if (preg_match('/' . preg_quote($keyword, '/') . '[^0-9]{0,15}' . $datePattern . '/iu', $text, $m)) {
                return $this->normalizeDate($m[1], $m[2], $m[3]);
            }
        }

        // Fallback: erstes Datum im Text
        if (preg_match('/' . $datePattern . '/', $text, $m)) {
            return $this->normalizeDate($m[1], $m[2], $m[3]);
        }

        return null;
    }

    private function normalizeDate(string $d, string $mo, string $y): ?string
    {
        $year = strlen($y) === 2 ? 2000 + (int) $y : (int) $y;

        try {
            return Carbon::create($year, (int) $mo, (int) $d)?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function iban(string $text): ?string
    {
        if (preg_match('/\b([A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){4,7}\s?[A-Z0-9]{1,3})\b/', strtoupper($text), $m)) {
            return preg_replace('/\s+/', '', $m[1]);
        }

        return null;
    }

    private function taxRate(string $text): ?float
    {
        if (preg_match('/\b(19|7)\s*%/', $text, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function grossAmount(string $text): ?float
    {
        // Höchste Priorität: ein direkt mit "=" ausgewiesener fälliger Betrag,
        // z. B. "fälligen Betrag (Euro) = 3.103,57" (Lotto-/Sammelabrechnungen).
        // Das "=" liegt unmittelbar vor dem Betrag und ist damit eindeutig.
        if (preg_match('/betrag[^0-9\-]{0,15}=\s*(\d{1,3}(?:[.\s]\d{3})*,\d{2})/iu', $text, $m)) {
            return $this->parseAmount($m[1]);
        }

        // Starke, eindeutige Gesamtbetrags-Schlüsselwörter zuerst (in Reihenfolge).
        // "gesamt(?!preis)" verhindert Falschtreffer auf der Spalte "Gesamtpreis".
        $keywords = [
            'endbetrag',
            'abrechnungsbetrag',
            'rechnungsbetrag',
            'rechnungssumme',
            'gesamtbetrag',
            'zu zahlender betrag',
            'zahlbetrag',
            'zu zahlen',
            'gesamtsumme',
            'gesamt(?!preis)',
            'brutto',
        ];

        foreach ($keywords as $keyword) {
            if (preg_match('/' . $keyword . '[^0-9\-]{0,25}(\d{1,3}(?:[.\s]\d{3})*,\d{2})/iu', $text, $m)) {
                return $this->parseAmount($m[1]);
            }
        }

        // Fallback: größter Betrag im Text
        if (preg_match_all('/(\d{1,3}(?:[.\s]\d{3})*,\d{2})/', $text, $all)) {
            $amounts = array_map(fn ($v) => $this->parseAmount($v), $all[1]);

            return $amounts ? max($amounts) : null;
        }

        return null;
    }

    private function taxAmount(string $text): ?float
    {
        $keywords = ['mwst', 'mehrwertsteuer', 'ust', 'umsatzsteuer', 'steuer'];

        foreach ($keywords as $keyword) {
            if (preg_match('/' . preg_quote($keyword, '/') . '[^0-9\-]{0,20}(\d{1,3}(?:[.\s]\d{3})*,\d{2})/iu', $text, $m)) {
                return $this->parseAmount($m[1]);
            }
        }

        return null;
    }

    private function parseAmount(string $value): float
    {
        $value = str_replace([' ', '.'], '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }
}
