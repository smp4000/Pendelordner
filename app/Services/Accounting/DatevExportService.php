<?php

namespace App\Services\Accounting;

use App\Enums\ChartOfAccounts;
use App\Models\AccountAssignment;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\DatevExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugt einen DATEV-Export im EXTF-Format „Buchungsstapel" (Modul 14).
 *
 * Es werden die kontierten Bankumsätze (account_assignments) eines Zeitraums
 * exportiert. Datei in Windows-1252 (DATEV-ANSI), Semikolon-getrennt,
 * Dezimalkomma. Belegdatum als TTMM bezogen auf das Wirtschaftsjahr.
 *
 * Hinweis: deckt die EXTF-Grundfelder (bis Buchungstext + KOST) ab; Berater-/
 * Mandantennummer und Wirtschaftsjahr werden aus den Parametern übernommen.
 */
class DatevExportService
{
    /** DATEV-EXTF-Spaltenüberschriften (feste Reihenfolge, Buchungsstapel). */
    private const COLUMNS = [
        'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz',
        'Kurs', 'Basis-Umsatz', 'WKZ Basis-Umsatz',
        'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'BU-Schlüssel',
        'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext',
        'Postensperre', 'Diverse Adressnummer', 'Geschäftspartnerbank',
        'Sachverhalt', 'Zinssperre', 'Beleglink',
        'Beleginfo - Art 1', 'Beleginfo - Inhalt 1', 'Beleginfo - Art 2', 'Beleginfo - Inhalt 2',
        'Beleginfo - Art 3', 'Beleginfo - Inhalt 3', 'Beleginfo - Art 4', 'Beleginfo - Inhalt 4',
        'Beleginfo - Art 5', 'Beleginfo - Inhalt 5', 'Beleginfo - Art 6', 'Beleginfo - Inhalt 6',
        'Beleginfo - Art 7', 'Beleginfo - Inhalt 7', 'Beleginfo - Art 8', 'Beleginfo - Inhalt 8',
        'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle',
    ];

    public function generate(
        Carbon $from,
        Carbon $to,
        ?Business $business = null,
        ?ChartOfAccounts $chart = null,
        string $consultant = '',
        string $client = '',
    ): DatevExport {
        $chart ??= ChartOfAccounts::from(config('pendelordner.kontierung.standard_kontenrahmen', 'skr03'));
        $fiscalYearStart = $from->copy()->startOfYear();

        $assignments = AccountAssignment::query()
            ->with('assignable', 'costCenter')
            ->where('assignable_type', (new BankTransaction())->getMorphClass())
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->when($business, fn ($q) => $q->whereHasMorph('assignable', [BankTransaction::class],
                fn ($q) => $q->where('business_id', $business->id)))
            ->orderBy('booking_date')
            ->get();

        $csv = $this->buildCsv($assignments, $from, $to, $fiscalYearStart, $chart, $consultant, $client);

        $name = 'DATEV_' . $from->format('Ymd') . '-' . $to->format('Ymd') . ($business ? '_' . $business->id : '') . '.csv';
        $path = 'exports/' . $name;
        Storage::disk('local')->put($path, $csv);

        return DatevExport::create([
            'business_id' => $business?->id,
            'label' => 'DATEV-Export ' . $from->format('d.m.Y') . '–' . $to->format('d.m.Y'),
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'consultant_number' => $consultant ?: null,
            'client_number' => $client ?: null,
            'chart_of_accounts' => $chart->value,
            'account_length' => 4,
            'fiscal_year_start' => (int) $fiscalYearStart->format('md'),
            'file_path' => $path,
            'entry_count' => $assignments->count(),
            'status' => 'generated',
        ]);
    }

    private function buildCsv(
        $assignments, Carbon $from, Carbon $to, Carbon $fiscalYearStart,
        ChartOfAccounts $chart, string $consultant, string $client,
    ): string {
        $lines = [];
        $lines[] = $this->headerLine($from, $to, $fiscalYearStart, $consultant, $client);
        $lines[] = $this->csvRow(self::COLUMNS);

        foreach ($assignments as $a) {
            /** @var AccountAssignment $a */
            $transaction = $a->assignable;
            $debitCredit = ($transaction && $transaction->amount < 0) ? 'S' : 'H';

            $row = array_fill(0, count(self::COLUMNS), '');
            $row[0] = number_format((float) $a->amount, 2, ',', '');      // Umsatz
            $row[1] = $debitCredit;                                       // Soll/Haben
            $row[2] = 'EUR';                                              // WKZ
            $row[6] = $a->account;                                        // Konto
            $row[7] = $a->contra_account;                                 // Gegenkonto
            $row[8] = $a->tax_key;                                        // BU-Schlüssel
            $row[9] = $a->booking_date?->format('dm');                    // Belegdatum TTMM
            $row[10] = $a->document_number;                               // Belegfeld 1
            $row[13] = $a->booking_text;                                  // Buchungstext
            $row[36] = $a->costCenter?->number;                           // KOST1

            $lines[] = $this->csvRow($row);
        }

        $content = implode("\r\n", $lines) . "\r\n";

        // DATEV erwartet Windows-1252 (ANSI).
        return mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
    }

    /** EXTF-Kopfzeile (Zeile 1). */
    private function headerLine(Carbon $from, Carbon $to, Carbon $fiscalYearStart, string $consultant, string $client): string
    {
        $fields = array_fill(0, 31, '');
        $fields[0] = 'EXTF';
        $fields[1] = '700';                 // Versionsnummer
        $fields[2] = '21';                  // Kategorie: Buchungsstapel
        $fields[3] = 'Buchungsstapel';
        $fields[4] = '7';                   // Formatversion
        $fields[5] = now()->format('YmdHis') . '000'; // Erzeugt am
        $fields[10] = $consultant;          // Beraternummer
        $fields[11] = $client;              // Mandantennummer
        $fields[12] = $fiscalYearStart->format('Ymd'); // WJ-Beginn
        $fields[13] = '4';                  // Sachkontenlänge
        $fields[14] = $from->format('Ymd');// Datum von
        $fields[15] = $to->format('Ymd');  // Datum bis
        $fields[16] = 'Pendelordner';      // Bezeichnung
        $fields[17] = '';                   // Diktatkürzel
        $fields[18] = '1';                  // Buchungstyp: Finanzbuchführung
        $fields[19] = '0';                  // Rechnungslegungszweck

        return $this->csvRow($fields, numericUnquoted: [1, 2, 4, 13, 18, 19]);
    }

    /**
     * Baut eine CSV-Zeile: Textfelder in Anführungszeichen, leere/numerische
     * Felder ohne. Standardmäßig werden nicht-leere Felder gequotet.
     *
     * @param  array<int, string|null>  $fields
     * @param  array<int, int>  $numericUnquoted
     */
    private function csvRow(array $fields, array $numericUnquoted = []): string
    {
        $out = [];
        foreach (array_values($fields) as $i => $value) {
            $value = (string) ($value ?? '');
            if ($value === '') {
                $out[] = '';
            } elseif (in_array($i, $numericUnquoted, true) || preg_match('/^-?\d+(,\d+)?$/', $value)) {
                $out[] = $value;
            } else {
                $out[] = '"' . str_replace('"', '""', $value) . '"';
            }
        }

        return implode(';', $out);
    }
}
