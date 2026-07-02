<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\PosSale;
use App\Services\Pos\PosReportImporter;
use App\Services\Pos\PosReportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosImportTest extends TestCase
{
    use RefreshDatabase;

    private function sampleCsv(): string
    {
        return implode("\n", [
            'Zeitbezogene Abrechnung (Monat),,,,,,,,',
            ',Aral STATION,,,Datum:,,01.07.2026 04:05:13,,',
            ',Christian Welle,,,Stationsnr.:,,0170320196,,',
            ',Schlitzer Str. 105,,,Abrechnungsnr.:,,32,,',
            ',bis 30.06.2026 23:59:59,,,,,,,',
            ',FN,,Artikelgruppe,,Menge,,Betrag EUR,EKW-Konto',
            ',1,,SuperPlus,,"5.718,63",,"11.774,47",1515',
            ',2,,Diesel,,"67.954,70",,"123.740,85",1515',
            ',3,,Benzin,,"0,00",,"0,00",1515',
            ',4,,Super E5,,"53.710,16",,"103.634,91",1515',
            ',5,,Super E10,,"29.323,61",,"54.733,55",1515',
            ',6,,KRAFTSTOFFE,,"156.707,10",,"293.883,78",',
            ',7,,------------------,,,,,',
            ',17,,Tabakwaren,,6.584,,"67.971,48",8140',
            ',33,,Waschanlage,,451,,"5.271,84",8190',
            ',42,,Lotto - Toto,,874,,"14.241,50",8090',
            ',49,,UMSATZ    E  G,,,,"116.522,52",',
            ',50,,UMSATZ     A  G,,,,"294.266,65",',
            ',128,,ec - Chip      E,,,,"41.514,11",101',
        ]);
    }

    public function test_parser_liest_station_monat_und_artikelgruppen(): void
    {
        $r = PosReportParser::parse($this->sampleCsv());

        $this->assertSame('0170320196', $r['station_number']);
        $this->assertSame(6, $r['period']->month);
        $this->assertSame(2026, $r['period']->year);

        // Nur Detailzeilen mit Konto vor „UMSATZ" (5 Kraftstoff + 3 Shop/Wäsche/Lotto).
        $this->assertCount(8, $r['rows']);

        // Zwischensumme KRAFTSTOFFE (ohne Konto) und Zeilen nach UMSATZ nicht dabei.
        $labels = array_column($r['rows'], 'article_group');
        $this->assertNotContains('KRAFTSTOFFE', $labels);
        $this->assertNotContains('ec - Chip      E', $labels);

        // Kraftstoff = Liter-Summe (Konto 1515).
        $fuelLiters = array_sum(array_map(
            fn ($x) => $x['is_fuel'] ? $x['quantity'] : 0,
            $r['rows'],
        ));
        $this->assertEqualsWithDelta(156707.10, $fuelLiters, 0.01);

        // Beispiel Shop-Zeile brutto.
        $tabak = collect($r['rows'])->firstWhere('article_group', 'Tabakwaren');
        $this->assertEqualsWithDelta(67971.48, $tabak['amount_gross'], 0.01);
        $this->assertFalse($tabak['is_fuel']);
    }

    public function test_import_ordnet_der_tankstelle_ueber_stationsnummer_zu(): void
    {
        $business = Business::create([
            'name' => 'Aral Fulda', 'type' => 'gas_station',
            'station_number' => '0170320196', 'fuel_commission_ct' => 2.8,
        ]);

        $res = (new PosReportImporter())->import($this->sampleCsv());

        $this->assertNotNull($res['business']);
        $this->assertSame($business->id, $res['business']->id);
        $this->assertSame(6, $res['period']->month);
        $this->assertSame(8, PosSale::where('business_id', $business->id)->count());

        // Kraftstoff-Liter und Provision (156.707,10 L × 2,8 ct = 4.387,80 €).
        $liters = (float) PosSale::where('business_id', $business->id)->where('is_fuel', true)->sum('quantity');
        $this->assertEqualsWithDelta(156707.10, $liters, 0.01);
        $this->assertEqualsWithDelta(4387.80, $liters * 2.8 / 100, 0.01);
    }
}
