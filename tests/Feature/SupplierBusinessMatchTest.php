<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Services\Ocr\OcrService;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Zuordnung von Rechnungen: Lieferant getrennt nach Kundennummer und
 * Tankstelle – jede Tankstelle hat beim Lieferanten ihre eigene Kundennummer.
 */
class SupplierBusinessMatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeReceipt(string $html): Receipt
    {
        Storage::fake('belege');
        Storage::disk('belege')->put('t/beleg.pdf', DomPdf::loadHTML($html)->output());

        return Receipt::create([
            'type' => 'incoming_invoice',
            'file_path' => 't/beleg.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_kundennummer_bestimmt_lieferant_und_tankstelle(): void
    {
        $fulda = Business::create(['name' => 'Aral Fulda', 'type' => 'gas_station']);
        $petersberg = Business::create(['name' => 'Aral Petersberg', 'type' => 'gas_station']);

        $supplier = Supplier::create(['name' => 'Lekkerland SE', 'active' => true]);
        $supplier->customerNumbers()->create(['business_id' => $fulda->id, 'customer_number' => '11145']);
        $supplier->customerNumbers()->create(['business_id' => $petersberg->id, 'customer_number' => '22288']);

        $receipt = $this->makeReceipt('<p>Rechnung</p><p>Kundennummer: 22288</p><p>Betrag 100,00 EUR</p>');
        (new OcrService())->process($receipt->refresh());

        $receipt->refresh();
        $this->assertSame('22288', $receipt->customer_number);
        $this->assertSame($supplier->id, $receipt->supplier_id);
        // Kundennummer 22288 gehört zur Tankstelle Petersberg.
        $this->assertSame($petersberg->id, $receipt->business_id);
    }

    public function test_ustid_erkennt_lieferant_und_kundennummer_die_tankstelle(): void
    {
        $fulda = Business::create(['name' => 'Aral Fulda', 'type' => 'gas_station']);
        $petersberg = Business::create(['name' => 'Aral Petersberg', 'type' => 'gas_station']);

        $supplier = Supplier::create(['name' => 'Hall Tabakwaren', 'vat_id' => 'DE123456789', 'active' => true]);
        // Gleiche Kundennummer existiert auch bei einem anderen Lieferanten ->
        // allein über die Nummer wäre der Lieferant nicht eindeutig.
        $other = Supplier::create(['name' => 'Anderer Lieferant', 'active' => true]);
        $other->customerNumbers()->create(['business_id' => $fulda->id, 'customer_number' => '5000']);
        $supplier->customerNumbers()->create(['business_id' => $petersberg->id, 'customer_number' => '5000']);

        $receipt = $this->makeReceipt('<p>Rechnung</p><p>USt-IdNr.: DE123456789</p><p>Kd.-Nr.: 5000</p>');
        (new OcrService())->process($receipt->refresh());

        $receipt->refresh();
        // Lieferant über die USt-IdNr eindeutig ...
        $this->assertSame($supplier->id, $receipt->supplier_id);
        // ... und die Kundennummer bestimmt DANN die Tankstelle (Petersberg).
        $this->assertSame($petersberg->id, $receipt->business_id);
    }
}
