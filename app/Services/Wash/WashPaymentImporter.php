<?php

namespace App\Services\Wash;

use App\Models\Business;
use App\Models\WashFreePlate;
use App\Models\WashPayment;
use Illuminate\Support\Carbon;

/**
 * Importiert den Karten-/PayPal-Zahlungs-Export der Waschanlage.
 *
 * - trennt automatisch nach Station (Text "… Fulda: …" / "… Petersberg: …"),
 * - "Subscription payment" hat keine Station -> business_id bleibt leer,
 * - erkennt Programm (Basis/Schnell/…/Abo) und Kennzeichen,
 * - Dublettenschutz über die Vorgangs-Id (external_id).
 *
 * Format: CSV mit optionaler "sep=;"-Zeile, Semikolon- (oder Tab-)getrennt,
 * englische Dezimalpunkte. Die Zahlart (card/paypal) wird beim Upload gewählt.
 */
class WashPaymentImporter
{
    /** @var array<string, ?Business> Cache der Stationsauflösung. */
    private array $bizCache = [];

    /**
     * @return array{imported:int,skipped:int,unassigned:int,byBusiness:array<string,int>}
     */
    public function import(string $csvContent, string $method = 'card'): array
    {
        $rows = $this->parseCsv($csvContent);

        $imported = 0;
        $skipped = 0;
        $unassigned = 0;
        $byBusiness = [];

        foreach ($rows as $r) {
            $externalId = trim((string) ($r['Id'] ?? ''));
            if ($externalId === '') {
                continue;
            }
            if (WashPayment::where('external_id', $externalId)->exists()) {
                $skipped++;

                continue;
            }

            $desc = (string) ($r['Description'] ?? '');
            [$business, $program, $plate, $isSub] = $this->classify($desc);

            $total = $this->money($r['Total'] ?? '0');
            $plateNorm = $plate ? WashFreePlate::normalize($plate) : null;

            WashPayment::create([
                'external_id' => $externalId,
                'business_id' => $business?->id,
                'payment_method' => $method,
                'created_source' => $this->date($r['Created'] ?? null),
                'payment_date' => substr((string) ($r['Payment date'] ?? ''), 0, 10) ?: now()->toDateString(),
                'customer_name' => trim((string) ($r['Customer name'] ?? '')) ?: null,
                'currency' => strtolower(trim((string) ($r['Currency'] ?? 'eur'))) ?: 'eur',
                'subtotal' => $this->money($r['Subtotal'] ?? '0'),
                'total' => $total,
                'tax' => $this->money($r['Tax'] ?? '0'),
                'discount' => $this->money($r['Discount'] ?? '0'),
                'application_fee' => $this->money($r['Application fee'] ?? '0'),
                'surcharge' => trim((string) ($r['Surcharge'] ?? '')) !== '' ? $this->money($r['Surcharge']) : null,
                'state_code' => is_numeric(trim((string) ($r['State'] ?? ''))) ? (int) $r['State'] : null,
                'description' => $desc,
                'program' => $program,
                'plate' => $plate,
                'plate_normalized' => $plateNorm,
                'is_subscription' => $isSub,
                'is_free' => abs($total) < 0.005,
            ]);

            $imported++;
            if ($business) {
                $key = $business->short_name ?: $business->city ?: (string) $business->id;
                $byBusiness[$key] = ($byBusiness[$key] ?? 0) + 1;
            } else {
                $unassigned++;
            }
        }

        return compact('imported', 'skipped', 'unassigned', 'byBusiness');
    }

    /**
     * Zerlegt den Description-Text in [Betrieb, Programm, Kennzeichen, Abo?].
     *
     * @return array{0: ?Business, 1: ?string, 2: ?string, 3: bool}
     */
    private function classify(string $desc): array
    {
        $lower = mb_strtolower($desc);

        // Abo/Flatrate hat keinen Programm- und keinen Stationstext.
        if (str_contains($lower, 'subscription')) {
            return [null, 'Abo', null, true];
        }

        $business = null;
        if (str_contains($lower, 'petersberg')) {
            $business = $this->business('Petersberg');
        } elseif (str_contains($lower, 'fulda')) {
            $business = $this->business('Fulda');
        }

        // Programm steht zwischen ":" und "(", das Kennzeichen in Klammern.
        $program = null;
        if (preg_match('/:\s*([^()]+?)\s*\(/u', $desc, $m)) {
            $program = trim($m[1]);
        }
        $plate = null;
        if (preg_match('/\(([^)]+)\)/u', $desc, $m2)) {
            $plate = trim($m2[1]);
        }

        return [$business, $program, $plate, false];
    }

    private function business(string $keyword): ?Business
    {
        return $this->bizCache[$keyword] ??= Business::where('city', 'like', "%{$keyword}%")
            ->orderBy('id')
            ->first();
    }

    /**
     * CSV robust einlesen: optionale "sep=;"-Zeile, Trennzeichen automatisch
     * erkennen, Kopfzeile als Spaltennamen.
     *
     * @return array<int, array<string, ?string>>
     */
    private function parseCsv(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM entfernen
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $delimiter = null;
        $start = 0;
        if (isset($lines[0]) && stripos(trim($lines[0]), 'sep=') === 0) {
            $delimiter = substr(trim($lines[0]), 4, 1) ?: ';';
            $start = 1;
        }

        $header = null;
        $rows = [];
        for ($i = $start, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];
            if (trim($line) === '') {
                continue;
            }
            $delimiter ??= $this->detectDelimiter($line);
            $cols = str_getcsv($line, $delimiter);
            if ($header === null) {
                $header = array_map(fn ($h) => trim((string) $h), $cols);

                continue;
            }
            $row = [];
            foreach ($header as $k => $name) {
                $row[$name] = $cols[$k] ?? null;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $best = ';';
        $bestCount = -1;
        foreach ([';', "\t", ','] as $d) {
            $count = substr_count($line, $d);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $d;
            }
        }

        return $best;
    }

    /** Betrag aus englischem (Punkt) oder deutschem (Komma) Format in float. */
    private function money(mixed $v): float
    {
        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }
        if (preg_match('/^-?\d+,\d{1,2}$/', $s)) {
            $s = str_replace(',', '.', $s);       // deutsches Format
        } else {
            $s = str_replace(',', '', $s);        // englische Tausender-Kommas
        }

        return (float) $s;
    }

    private function date(mixed $v): ?Carbon
    {
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
