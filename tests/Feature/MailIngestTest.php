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

        // Eine Mail mit vier verschiedenen Rechnungen als Anhang.
        $r1 = $cmd->storeAttachmentAsReceipt('%PDF Rechnung 1', '2505511.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r2 = $cmd->storeAttachmentAsReceipt('%PDF Rechnung 2', '2606680.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r3 = $cmd->storeAttachmentAsReceipt('%PDF Rechnung 3', '2606818.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');
        $r4 = $cmd->storeAttachmentAsReceipt('%PDF Rechnung 4', '2606820.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni');

        // Vier separate Belege, jeder mit eigenem Dateinamen.
        $this->assertSame(4, Receipt::count());
        $this->assertSame(
            ['2505511.pdf', '2606680.pdf', '2606818.pdf', '2606820.pdf'],
            Receipt::orderBy('id')->pluck('file_name')->all()
        );
        $this->assertCount(4, collect([$r1->id, $r2->id, $r3->id, $r4->id])->unique());

        // Gleiche Datei erneut (z. B. Mail doppelt) -> Dublette, kein neuer Beleg.
        $this->assertNull($cmd->storeAttachmentAsReceipt('%PDF Rechnung 1', '2505511-kopie.pdf', 'pdf', 'application/pdf', 'Rechnungen Juni'));
        $this->assertSame(4, Receipt::count());

        // Nicht zugelassene Endung (z. B. Signaturbild) -> kein Beleg.
        $this->assertNull($cmd->storeAttachmentAsReceipt('logo-daten', 'signatur.svg', 'svg', 'image/svg+xml', 'Rechnungen Juni'));
        $this->assertSame(4, Receipt::count());
    }
}
