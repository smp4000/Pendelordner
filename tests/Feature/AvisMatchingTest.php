<?php

namespace Tests\Feature;

use App\Filament\Pages\Kontoumsatzdetails;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Matching\MatchingEngine;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AvisMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_zahlungsavis_schlaegt_die_einzelrechnungen_vor(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $account = BankAccount::create(['label' => 'Geschäftskonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $supplier = Supplier::create(['name' => 'SB Union Großmarkt GmbH', 'active' => true]);

        // Sammel-Lastschrift über 524,37 €.
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-25', 'counterparty' => 'SB UNION Großmarkt GmbH',
            'amount' => -524.37, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        // Das Avis (nennt die drei Rechnungsnummern + Gesamtsumme).
        $advice = Receipt::create([
            'type' => 'incoming_invoice', 'supplier_id' => $supplier->id, 'gross_amount' => 524.37,
            'file_path' => '2026/06/avis.pdf', 'mime_type' => 'application/pdf',
            'ocr_text' => 'Lastschriftankuendigung 601957562 RE1356294 14.06.2026 286,33 '
                . 'RE1359225 18.06.2026 217,57 RE1360314 19.06.2026 20,47 Gesamt-Summe 524,37',
        ]);

        // Die drei Einzelrechnungen.
        $r1 = Receipt::create(['type' => 'incoming_invoice', 'supplier_id' => $supplier->id, 'invoice_number' => 'RE1356294', 'gross_amount' => 286.33]);
        $r2 = Receipt::create(['type' => 'incoming_invoice', 'supplier_id' => $supplier->id, 'invoice_number' => 'RE1359225', 'gross_amount' => 217.57]);
        $r3 = Receipt::create(['type' => 'incoming_invoice', 'supplier_id' => $supplier->id, 'invoice_number' => 'RE1360314', 'gross_amount' => 20.47]);

        // Engine erkennt Avis + die drei Rechnungen.
        $result = (new MatchingEngine())->suggestFromAdvice($tx);
        $this->assertNotNull($result);
        $this->assertSame($advice->id, $result['advice']->id);
        $this->assertEqualsWithDelta(524.37, $result['sum'], 0.001);
        $this->assertEqualsCanonicalizing(
            [$r1->id, $r2->id, $r3->id],
            $result['invoices']->pluck('id')->all()
        );

        // Über die Seite: ein Klick ordnet alle drei zu -> vollständig zugeordnet.
        Livewire::test(Kontoumsatzdetails::class)
            ->set('selectedTransactionId', $tx->id)
            ->call('attachAdviceInvoices');

        $this->assertSame(3, $tx->fresh()->receipts()->count());
        $this->assertEqualsWithDelta(0.0, (float) $tx->fresh()->difference, 0.001);
    }

    public function test_rechnungsnummern_im_verwendungszweck_ohne_avis(): void
    {
        $this->seed(DatabaseSeeder::class);

        $account = BankAccount::create(['label' => 'Geschäftskonto', 'business_id' => Business::first()->id, 'currency' => 'EUR']);

        // Sammel-Lastschrift, deren Verwendungszweck die Rechnungsnummern nennt
        // (wie im echten SEPA-Text) – KEIN Avis hochgeladen.
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-25', 'counterparty' => 'SB UNION Großmarkt GmbH',
            'purpose' => 'RE1356294 14.6.2026RE1359225 18.6.2026RE1360314 19.6.2026 EREF: 0601957562',
            'amount' => -524.37, 'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);

        $r1 = Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'RE1356294', 'gross_amount' => 286.33]);
        $r2 = Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'RE1359225', 'gross_amount' => 217.57]);
        $r3 = Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'RE1360314', 'gross_amount' => 20.47]);

        $result = (new MatchingEngine())->suggestFromAdvice($tx);
        $this->assertNotNull($result);
        $this->assertNull($result['advice']); // Quelle ist der Verwendungszweck, kein Beleg
        $this->assertEqualsCanonicalizing([$r1->id, $r2->id, $r3->id], $result['invoices']->pluck('id')->all());
    }

    public function test_kein_avis_kein_vorschlag(): void
    {
        $this->seed(DatabaseSeeder::class);

        $account = BankAccount::create(['label' => 'K', 'business_id' => Business::first()->id, 'currency' => 'EUR']);
        $tx = BankTransaction::create([
            'bank_account_id' => $account->id, 'business_id' => $account->business_id,
            'booking_date' => '2026-06-25', 'counterparty' => 'Irgendwer', 'amount' => -100.00,
            'reviewed' => false, 'dedup_hash' => bin2hex(random_bytes(16)),
        ]);
        // Ein einzelner passender Beleg ist KEIN Sammel-Avis.
        Receipt::create(['type' => 'incoming_invoice', 'invoice_number' => 'X1', 'gross_amount' => 100.00]);

        $this->assertNull((new MatchingEngine())->suggestFromAdvice($tx));
    }
}
