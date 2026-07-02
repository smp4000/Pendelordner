<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\CostCenter;
use App\Models\LedgerAccount;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Services\Matching\SupplierDefaults;
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

    public function test_zuordnung_setzt_kostenstelle_und_edtas_konto_je_kundennummer(): void
    {
        $fulda = Business::create(['name' => 'Aral Fulda', 'type' => 'gas_station']);
        $petersberg = Business::create(['name' => 'Aral Petersberg', 'type' => 'gas_station']);
        $ccFulda = CostCenter::create(['name' => 'Tankstelle Fulda', 'business_id' => $fulda->id, 'active' => true]);
        $ccPetersberg = CostCenter::create(['name' => 'Tankstelle Petersberg', 'business_id' => $petersberg->id, 'active' => true]);
        $ledger = LedgerAccount::firstOrCreate(['chart' => 'edtas', 'number' => '3140'], ['name' => 'Einkauf Tabakwaren A']);

        $supplier = Supplier::create(['name' => 'Hall Tabakwaren', 'active' => true]);
        $supplier->customerNumbers()->create([
            'business_id' => $fulda->id, 'customer_number' => '11145',
            'cost_center_id' => $ccFulda->id, 'edtas_account' => '3140',
        ]);
        $supplier->customerNumbers()->create([
            'business_id' => $petersberg->id, 'customer_number' => '22288',
            'cost_center_id' => $ccPetersberg->id, 'edtas_account' => '3140',
        ]);

        $account = BankAccount::create(['label' => 'GS Fulda', 'business_id' => $fulda->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $fulda->id,
            'booking_date' => '2026-06-01', 'counterparty' => 'Hall Tabakwaren KG',
            'amount' => -11556.71, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Beleg mit Kundennummer 11145 (Fulda) und erkanntem Lieferanten.
        $receipt = Receipt::create([
            'type' => 'incoming_invoice', 'supplier_id' => $supplier->id,
            'customer_number' => '11145', 'business_id' => $fulda->id, 'gross_amount' => 11556.71,
        ]);
        $tx->receipts()->attach($receipt->id, ['amount' => 11556.71]);

        (new SupplierDefaults())->applyToTransaction($tx, $receipt);

        $tx->refresh();
        $this->assertSame($supplier->id, $tx->supplier_id);
        $this->assertSame($ccFulda->id, $tx->cost_center_id);      // Kostenstelle der Kundennummer Fulda
        $this->assertSame($ledger->id, $tx->ledger_account_id);    // eDTAS-Konto 3140

        // Manuell gesetzte Felder werden NICHT überschrieben.
        $tx2 = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $fulda->id,
            'booking_date' => '2026-06-02', 'counterparty' => 'Hall',
            'amount' => -100, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
            'cost_center_id' => $ccPetersberg->id,
        ]);
        (new SupplierDefaults())->applyToTransaction($tx2, $receipt);
        $this->assertSame($ccPetersberg->id, $tx2->fresh()->cost_center_id);
    }
}
