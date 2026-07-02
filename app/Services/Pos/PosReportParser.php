<?php

namespace App\Services\Pos;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Liest die Aral Back-Office „Zeitbezogene Abrechnung (Monat)" (CSV) ein.
 *
 * Aus dem Kopf werden Stationsnummer und Abrechnungsmonat („bis TT.MM.JJJJ")
 * gelesen. Aus dem Erlös-Teil (bis zur Zeile „UMSATZ …") die Artikelgruppen
 * mit Menge, Bruttobetrag und EKW-Konto. Kraftstoffzeilen (Konto 1515) führen
 * die Menge in Litern – daraus wird später die Provision berechnet.
 */
class PosReportParser
{
    /**
     * @return array{station_number: ?string, period: ?Carbon, rows: list<array{fn:int, article_group:string, quantity:float, amount_gross:float, ekw_konto:string, is_fuel:bool}>}
     */
    public static function parse(string $content): array
    {
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        $station = null;
        $period = null;
        $rows = [];

        foreach ($lines as $line) {
            if ($station === null && preg_match('/Stationsnr[^0-9]*([0-9]{6,})/', $line, $m)) {
                $station = $m[1];
            }
            if ($period === null && preg_match('/bis\s+(\d{2}\.\d{2}\.\d{4})/', $line, $m)) {
                try {
                    $period = Carbon::createFromFormat('d.m.Y', $m[1])->startOfMonth();
                } catch (Throwable $e) {
                    // ignorieren, Fallback bleibt null
                }
            }

            $f = str_getcsv($line, ',', '"');
            $group = trim($f[3] ?? '');
            if ($group === '') {
                continue;
            }
            // Der Erlös-Teil endet bei der Summenzeile „UMSATZ …".
            if (stripos($group, 'UMSATZ') !== false) {
                break;
            }
            $konto = trim($f[8] ?? '');
            if (! preg_match('/^\d+$/', $konto)) {
                continue;   // Zwischensummen/Trennzeilen/Kopf haben kein Konto
            }

            $rows[] = [
                'fn' => (int) trim($f[1] ?? '0'),
                'article_group' => $group,
                'quantity' => self::num($f[5] ?? ''),
                'amount_gross' => self::num($f[7] ?? ''),
                'ekw_konto' => $konto,
                'is_fuel' => $konto === '1515',
            ];
        }

        return ['station_number' => $station, 'period' => $period, 'rows' => $rows];
    }

    /** Deutsche Zahl "156.707,10" / "-25,00" in float. */
    private static function num(string $s): float
    {
        $s = trim($s);
        if ($s === '') {
            return 0.0;
        }
        $s = str_replace(['.', ' '], '', $s);
        $s = str_replace(',', '.', $s);

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
