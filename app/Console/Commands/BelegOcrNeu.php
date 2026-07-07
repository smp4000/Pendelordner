<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use App\Services\Ocr\OcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Führt die OCR/Texterkennung für einen oder alle textlosen Belege erneut aus
 * und meldet, ob aus dem PDF Text gewonnen werden konnte. Hilft, Belege ohne
 * erkannte Daten (OCR=NEIN) nachträglich einzulesen bzw. das Problem
 * einzugrenzen (liefert das PDF eingebetteten Text oder ist es ein Scan/Bild?).
 */
class BelegOcrNeu extends Command
{
    protected $signature = 'belege:ocr-neu
        {id? : Beleg-ID; ohne Angabe alle Belege ohne Text}
        {--limit=200 : Maximalzahl bei Massenlauf}
        {--pause=1500 : Pause in Millisekunden zwischen den Belegen (schont das Cloud-OCR-Limit)}';

    protected $description = 'OCR für Belege erneut ausführen (und diagnostizieren)';

    public function handle(): int
    {
        $id = $this->argument('id');

        $receipts = $id
            ? Receipt::whereKey((int) $id)->get()
            : Receipt::query()
                ->where(fn ($q) => $q->whereNull('ocr_text')->orWhere('ocr_text', ''))
                ->limit((int) $this->option('limit'))->get();

        if ($receipts->isEmpty()) {
            $this->info('Keine passenden Belege gefunden.');

            return self::SUCCESS;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $service = new OcrService();
        $mitText = 0;

        foreach ($receipts as $receipt) {
            $abs = $receipt->file_path ? $disk->path($receipt->file_path) : null;
            $exists = $abs && is_file($abs);

            // Diagnose: liefert das PDF eingebetteten Text (smalot)?
            $rawLen = 0;
            if ($exists) {
                try {
                    $rawLen = mb_strlen(trim((new PdfParser())->parseFile($abs)->getText()));
                } catch (Throwable $e) {
                    $rawLen = -1; // Parser-Fehler
                }
            }

            // Vollständige OCR erneut ausführen (smalot + ggf. Tesseract) und speichern.
            if ($exists) {
                $service->process($receipt->refresh());
                $receipt->refresh();
            }

            $textLen = mb_strlen(trim((string) $receipt->ocr_text));
            if ($textLen > 0) {
                $mitText++;
            }

            $this->line(sprintf(
                '#%d  Datei=%s  vorhanden=%s  PDF-Text=%s  Ergebnis-Text=%d  Nr="%s"  Betrag=%s',
                $receipt->id,
                $receipt->file_name,
                $exists ? 'ja' : 'NEIN',
                $rawLen < 0 ? 'Parser-Fehler' : $rawLen . ' Zeichen',
                $textLen,
                (string) $receipt->invoice_number,
                number_format((float) $receipt->gross_amount, 2, ',', '.')
            ));

            // Kurze Pause, damit wir das Cloud-OCR-Tempo-Limit nicht überrennen.
            $pause = (int) $this->option('pause');
            if ($pause > 0 && $receipts->count() > 1) {
                usleep($pause * 1000);
            }
        }

        $this->newLine();
        $this->info($mitText . ' von ' . $receipts->count() . ' Beleg(en) haben jetzt Text.');
        $this->line('PDF-Text = 0 Zeichen bedeutet: kein eingebetteter Text (Scan/Bild-PDF) – dafür wird Tesseract-OCR benötigt.');

        return self::SUCCESS;
    }
}
