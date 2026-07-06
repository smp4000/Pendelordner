<?php

namespace Tests\Feature;

use App\Console\Commands\FetchInvoiceEmails;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MailIngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_mehrere_anhaenge_einer_mail_werden_zu_einzelnen_belegen(): void
    {
        Storage::fake('belege');
        $cmd = new FetchInvoiceEmails();

        // Echte PDF-Rechnungen sind größer als die Mindestgröße.
        $pdf = fn (int $i) => '%PDF-1.4 Rechnung ' . $i . ' ' . str_repeat('x', 7000);

        // Eine Mail mit vier verschiedenen Rechnungen als Anhang.
        $r1 = $cmd->storeAttachmentAsReceipt($pdf(1), '2505511.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r2 = $cmd->storeAttachmentAsReceipt($pdf(2), '2606680.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r3 = $cmd->storeAttachmentAsReceipt($pdf(3), '2606818.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r4 = $cmd->storeAttachmentAsReceipt($pdf(4), '2606820.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');

        // Vier separate Belege, jeder mit eigenem Dateinamen.
        $this->assertSame(4, Receipt::count());
        $this->assertSame(
            ['2505511.pdf', '2606680.pdf', '2606818.pdf', '2606820.pdf'],
            Receipt::orderBy('id')->pluck('file_name')->all()
        );
        $this->assertCount(4, collect([$r1->id, $r2->id, $r3->id, $r4->id])->unique());

        // Gleiche Datei erneut (z. B. Mail doppelt) -> Dublette, kein neuer Beleg.
        $this->assertNull($cmd->storeAttachmentAsReceipt($pdf(1), '2505511-kopie.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni'));
        $this->assertSame(4, Receipt::count());
    }

    public function test_signatur_logos_werden_nicht_als_beleg_importiert(): void
    {
        Storage::fake('belege');
        $cmd = new FetchInvoiceEmails();

        // Bild-Anhang (Logo/Signatur, z. B. "35 Jahre"-Grafik) -> Standard ist
        // PDF-only, also kein Beleg.
        $this->assertNull($cmd->storeAttachmentAsReceipt(str_repeat('PNG', 5000), 'logo-35-jahre.png', 'png', 'image/png', 'Rechnung'));

        // Sehr kleines PDF (z. B. Logo als PDF) -> unter Mindestgröße, kein Beleg.
        $this->assertNull($cmd->storeAttachmentAsReceipt('%PDF-1.4 mini', 'logo.pdf', 'pdf', 'application/pdf', 'Rechnung'));

        $this->assertSame(0, Receipt::count());
    }

    public function test_aufraeum_befehl_entfernt_importierte_logo_bilder(): void
    {
        Storage::fake('belege');
        Storage::disk('belege')->put('2026/07/logo.png', 'PNG');

        // Per Mail importiertes Logo-Bild ohne Zuordnung/Rechnungsnummer.
        $logo = Receipt::create([
            'type' => 'incoming_invoice', 'file_path' => '2026/07/logo.png',
            'mime_type' => 'image/png', 'note' => 'Per E-Mail empfangen: Rechnung',
        ]);
        // Echte Rechnung als PDF -> darf NICHT gelöscht werden.
        $rechnung = Receipt::create([
            'type' => 'incoming_invoice', 'invoice_number' => 'RG-1', 'gross_amount' => 100,
            'file_path' => '2026/07/rg.pdf', 'mime_type' => 'application/pdf',
            'note' => 'Per E-Mail empfangen: Rechnung',
        ]);

        // Trockenlauf löscht nichts.
        $this->artisan('belege:mail-bilder-aufraeumen')->assertSuccessful();
        $this->assertNotNull(Receipt::find($logo->id));

        // Mit --force wird nur das Logo gelöscht, die Rechnung bleibt.
        $this->artisan('belege:mail-bilder-aufraeumen --force')->assertSuccessful();
        $this->assertNull(Receipt::find($logo->id));
        $this->assertNotNull(Receipt::find($rechnung->id));
        Storage::disk('belege')->assertMissing('2026/07/logo.png');
    }
}
