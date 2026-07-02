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

    /**
     * Kundennummer beim Lieferanten, z. B. "Kundennummer  A8319" oder
     * "Kd.-Nr.: 12345". Berücksichtigt Tabellenlayouts, bei denen der Wert
     * VOR dem Label ("11145" ↵ "Kundennr.") oder in der Folgezeile steht –
     * auch mit dem Datum verklebt ("Kundennr. Datum" ↵ "1114503.07.26").
     */
    private function customerNumber(string $text): ?string
    {
        $label = '/(?:kunden(?:nummer|nr)|kd\.?[\s\-]*nr|debitor(?:en)?[\s\-]*(?:nr|nummer))\.?\s*[:#]?/i';

        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $i => $line) {
            if (! preg_match($label, $line, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            // 1) Kandidat im Zeilenrest hinter dem Label ("Kundennr.: 12345").
            $rest = substr($line, $m[0][1] + strlen($m[0][0]));
            if ($c = $this->customerNumberCandidate($rest)) {
                return $c;
            }

            // 2) Wert in der Zeile davor – reines Nummern-Token ("11145" ↵ "Kundennr.").
            if ($i > 0 && preg_match('/^\s*([A-Z]?\d{3,10})\s*$/i', $lines[$i - 1], $p)) {
                return $p[1];
            }

            // 3) Wert in der nächsten nicht-leeren Zeile (Tabellenkopf) –
            //    ggf. mit dem Datum verklebt ("1114503.07.26").
            for ($j = $i + 1; $j <= min($i + 2, count($lines) - 1); $j++) {
                $next = trim($lines[$j]);
                if ($next === '') {
                    continue;
                }
                if (preg_match('/^(\d{3,10}?)(\d{2}\.\d{2}\.\d{2,4})\b/', $next, $p)) {
                    return $p[1];
                }
                if ($c = $this->customerNumberCandidate($next)) {
                    return $c;
                }
                break; // nur die erste nicht-leere Folgezeile prüfen
            }
        }

        // Lotto-Abrechnungen: die Verkaufsstellennummer entspricht der
        // Kundennummer ("Verkaufsstelle 12792").
        if (preg_match('/verkaufsstellen?[\s\-]*(?:nr\.?|nummer)?\s*[:#]?\s*(\d{3,10})\b/i', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Erstes plausibles Kundennummer-Token: muss eine Ziffer enthalten, kein Datum. */
    private function customerNumberCandidate(string $s): ?string
    {
        if (! preg_match('/([A-Z0-9][A-Z0-9\-\/]{1,14})/i', trim($s), $m)) {
            return null;
        }
        $token = trim($m[1]);

        // Muss eine Ziffer enthalten (schließt Wörter wie "Datum" aus) und
        // darf kein Datum sein.
        if (! preg_match('/\d/', $token) || preg_match('/^\d{1,2}\.\d{1,2}\./', $token)) {
            return null;
        }

        return $token;
    }

    /** USt-IdNr. (z. B. "DE812499727") aus dem Text – separat, da keine Beleg-Spalte. */
    public function vatId(string $text): ?string
    {
        if (preg_match('/ust[-.\s]*id[-.\s]*nr\.?\s*[:#]?\s*([A-Z]{2}\s?\d{8,12})/i', $text, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }
        // Fallback: irgendeine DE-USt-IdNr im Text
        if (preg_match('/\b(DE\s?\d{9})\b/i', $text, $m)) {
            return strtoupper(preg_replace('/\s+/', '', $m[1]));
        }

        return null;
    }

    /** Bester Namensvorschlag für den Lieferanten: Firmenname inkl. Rechtsform. */
    public function supplierNameGuess(string $text): ?string
    {
        $text = str_replace('<>', '', $text);

        // Bis zu 3 großgeschriebene Wörter direkt vor der Rechtsform; dadurch
        // wird z. B. aus "... der Lekkerland SE" sauber "Lekkerland SE".
        $pattern = '/((?:[A-ZÄÖÜ][\wÄÖÜäöüß.\-&]*\s+){0,3}'
            . '(?:GmbH(?:\s*&\s*Co\.?\s*KG)?|AG|SE|KG|UG(?:\s*\(haftungsbeschränkt\))?|GbR|mbH|OHG|e\.\s?K\.|e\.\s?V\.))/u';

        if (preg_match($pattern, $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
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

        // Summen-/Steuertabellen-Zeile, die mit "<Betrag> EUR" endet und mehrere
        // Beträge enthält (z. B. Lekkerland: "2.010,00 … 1.946,57 EUR"). Der
        // letzte Betrag vor "EUR" ist der Zahlbetrag.
        $amountRe = '\d{1,3}(?:[.\s]\d{3})*,\d{2}';
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            // Steuer-Ausweiszeilen ("MwSt 19,00 % von: 1.188,40 EUR  225,80EUR")
            // sind nie der Zahlbetrag – überspringen.
            if (preg_match('/mwst|mehrwertsteuer|umsatzsteuer|\bust\b/iu', $line)) {
                continue;
            }
            if (preg_match('/(' . $amountRe . ')-?\s*EUR\s*$/u', $line, $lm)
                && preg_match_all('/' . $amountRe . '/u', $line) >= 3) {
                return $this->parseAmount($lm[1]);
            }
        }

        // Betrag unmittelbar VOR einem Zahlungs-Schlüsselwort (Fußzeilen-Layout,
        // z. B. Hall Tabakwaren: "6.452,24Zahlungsvereinbarung: SEPA-…"). Das
        // ist der tatsächliche Zahlbetrag – er schlägt Katalog-/KVP-Summen.
        if (preg_match('/(-?' . $amountRe . ')\s*(?:zahlungsvereinbarung|zahlbetrag|zahlbar\b)/iu', $text, $m)) {
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
