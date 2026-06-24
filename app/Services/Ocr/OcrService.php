<?php

namespace App\Services\Ocr;

use App\Enums\OcrStatus;
use App\Models\Receipt;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

/**
 * OCR-/Texterkennung für Belege (Modul 3).
 *
 * Strategie: Bei PDFs zuerst den eingebetteten Text lesen (schnell, exakt) und
 * nur bei zu wenig Text auf Tesseract-OCR ausweichen. Bilder (JPG/PNG/TIFF)
 * gehen direkt durch Tesseract. Aus dem Text werden anschließend Rechnungsdaten
 * extrahiert und – sofern noch leer – in den Beleg übernommen.
 */
class OcrService
{
    public function __construct(
        private readonly ReceiptParser $extractor = new ReceiptParser(),
    ) {}

    /**
     * Führt OCR für einen Beleg aus und speichert Text + erkannte Felder.
     */
    public function process(Receipt $receipt): Receipt
    {
        if (! $receipt->file_path) {
            return $receipt;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $absolutePath = $disk->path($receipt->file_path);

        try {
            $text = $this->extractText($absolutePath, (string) $receipt->mime_type);

            $receipt->ocr_text = $text;
            $receipt->ocr_status = trim($text) === '' ? OcrStatus::Failed->value : OcrStatus::Processed->value;
            $receipt->ocr_processed_at = now();

            if (trim($text) !== '') {
                $this->fillFromText($receipt, $text);
            }
        } catch (Throwable $e) {
            report($e);
            $receipt->ocr_status = OcrStatus::Failed->value;
            $receipt->ocr_processed_at = now();
        }

        $receipt->saveQuietly();

        return $receipt;
    }

    /**
     * Reiner Texte­xtraktor (ohne Persistenz) – auch für Tests/CLI nutzbar.
     */
    public function extractText(string $absolutePath, string $mimeType = ''): string
    {
        $isPdf = $mimeType === 'application/pdf' || str_ends_with(strtolower($absolutePath), '.pdf');

        if ($isPdf) {
            $text = $this->extractPdfText($absolutePath);
            $minLength = (int) config('pendelordner.ocr.pdf_text_mindestlaenge', 80);

            if (mb_strlen(trim($text)) >= $minLength) {
                return $text;
            }
            // Zu wenig eingebetteter Text -> Bild-OCR versuchen (falls möglich).
            return $this->ocrImage($absolutePath) ?: $text;
        }

        return $this->ocrImage($absolutePath) ?? '';
    }

    private function extractPdfText(string $path): string
    {
        try {
            return (new PdfParser())->parseFile($path)->getText();
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    private function ocrImage(string $path): ?string
    {
        try {
            $ocr = new TesseractOCR($path);
            $executable = config('pendelordner.ocr.tesseract_pfad');
            if ($executable && $executable !== 'tesseract') {
                $ocr->executable($executable);
            }
            $ocr->lang(config('pendelordner.ocr.sprache', 'deu'));

            return $ocr->run();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function fillFromText(Receipt $receipt, string $text): void
    {
        $data = $this->extractor->extract($text);

        foreach ($data as $field => $value) {
            if ($value !== null && blank($receipt->{$field})) {
                $receipt->{$field} = $value;
            }
        }

        // Netto/Steuer aus Brutto + Satz ergänzen, falls nur Brutto erkannt wurde.
        if ($receipt->gross_amount && $receipt->tax_rate && blank($receipt->tax_amount)) {
            $net = round((float) $receipt->gross_amount / (1 + (float) $receipt->tax_rate / 100), 2);
            $receipt->net_amount = $net;
            $receipt->tax_amount = round((float) $receipt->gross_amount - $net, 2);
        }
    }
}
