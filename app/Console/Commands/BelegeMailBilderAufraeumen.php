<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Räumt versehentlich per E-Mail importierte Bild-Anhänge (Logos/Signaturen)
 * auf, die als leere Belege gelandet sind. Betroffen sind nur Belege, die
 *  - per E-Mail empfangen wurden (Notiz "Per E-Mail empfangen"),
 *  - ein Bild sind (mime_type image/*),
 *  - keinem Umsatz zugeordnet sind und
 *  - keine Rechnungsnummer haben.
 *
 * Standard: nur anzeigen (Trockenlauf). Mit --force wird tatsächlich gelöscht.
 */
class BelegeMailBilderAufraeumen extends Command
{
    protected $signature = 'belege:mail-bilder-aufraeumen {--force : Wirklich löschen (sonst nur anzeigen)}';

    protected $description = 'Per E-Mail importierte Logo-/Signatur-Bilder ohne Zuordnung entfernen';

    public function handle(): int
    {
        $query = Receipt::query()
            ->unallocated()
            ->whereNull('invoice_number')
            ->where('mime_type', 'like', 'image/%')
            ->where('note', 'like', 'Per E-Mail empfangen%');

        $receipts = $query->get();

        if ($receipts->isEmpty()) {
            $this->info('Keine passenden Logo-/Signatur-Belege gefunden.');

            return self::SUCCESS;
        }

        $this->line($receipts->count() . ' Beleg(e) betroffen:');
        foreach ($receipts as $r) {
            $this->line('  #' . $r->id . '  ' . $r->file_name . '  (' . $r->mime_type . ')');
        }

        if (! $this->option('force')) {
            $this->warn('Trockenlauf – nichts gelöscht. Zum Löschen mit --force erneut ausführen.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $deleted = 0;
        foreach ($receipts as $r) {
            if ($r->file_path && $disk->exists($r->file_path)) {
                $disk->delete($r->file_path);
            }
            $r->forceDelete();
            $deleted++;
        }

        $this->info("✓ {$deleted} Beleg(e) gelöscht.");

        return self::SUCCESS;
    }
}
