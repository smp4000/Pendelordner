<?php

namespace App\Services\Pos;

use App\Models\Business;
use App\Models\PosSale;

/**
 * Importiert eine Aral-Kassenabrechnung (CSV) und speichert die Kassenumsätze
 * je Tankstelle und Monat. Die Tankstelle wird über die Stationsnummer
 * zugeordnet; bereits vorhandene Werte des Monats werden ersetzt.
 */
class PosReportImporter
{
    /**
     * @return array{business: ?Business, period: ?\Illuminate\Support\Carbon, station: ?string, count: int}
     */
    public function import(string $content): array
    {
        $p = PosReportParser::parse($content);

        $business = $p['station_number']
            ? Business::where('station_number', $p['station_number'])->first()
            : null;

        if (! $business || ! $p['period'] || empty($p['rows'])) {
            return ['business' => $business, 'period' => $p['period'], 'station' => $p['station_number'], 'count' => 0];
        }

        $period = $p['period'];

        // Monat ersetzen (Re-Import überschreibt).
        PosSale::where('business_id', $business->id)
            ->whereYear('period', $period->year)
            ->whereMonth('period', $period->month)
            ->delete();

        foreach ($p['rows'] as $row) {
            PosSale::create([
                'business_id' => $business->id,
                'period' => $period,
                'fn' => $row['fn'],
                'article_group' => $row['article_group'],
                'quantity' => $row['quantity'],
                'amount_gross' => $row['amount_gross'],
                'ekw_konto' => $row['ekw_konto'],
                'is_fuel' => $row['is_fuel'],
            ]);
        }

        return ['business' => $business, 'period' => $period, 'station' => $p['station_number'], 'count' => count($p['rows'])];
    }
}
