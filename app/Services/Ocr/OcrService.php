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
            // WICHTIG: nach UTF-8 wandeln. smalot liefert bei manchen PDFs
            // Latin-1/Windows-1252 (ß, ä, ü …); ungewandelt lehnt MySQL (utf8mb4)
            // das Speichern ab ("Incorrect string value") und die OCR bliebe leer.
            $text = self::ensureUtf8($this->extractText($absolutePath, (string) $receipt->mime_type));

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

        try {
            $receipt->saveQuietly();
        } catch (Throwable $e) {
            // Notfall: defekten Text verwerfen, damit ein einzelner Beleg den
            // Import/Lauf nicht abbricht – Status als fehlgeschlagen sichern.
            report($e);
            $receipt->ocr_text = null;
            $receipt->ocr_status = OcrStatus::Failed->value;
            $receipt->saveQuietly();
        }

        return $receipt;
    }

    /**
     * Stellt sicher, dass der Text gültiges UTF-8 ist. Bereits gültiges UTF-8
     * bleibt unverändert; sonst wird von Windows-1252 (Superset von ISO-8859-1)
     * gewandelt, notfalls werden ungültige Bytes entfernt.
     */
    public static function ensureUtf8(string $text): string
    {
        if ($text === '' || mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $text);
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

        $this->matchSupplier($receipt, $text);
        $this->flagContentDuplicate($receipt);
    }

    /**
     * Markiert den Beleg als mögliche Dublette, wenn bereits ein anderer Beleg
     * mit derselben Rechnungsnummer existiert (gleicher Lieferant – oder
     * gleicher Bruttobetrag, falls kein Lieferant erkannt wurde). Der Beleg
     * wird dadurch isoliert, bis der Nutzer entscheidet (löschen/behalten).
     */
    private function flagContentDuplicate(Receipt $receipt): void
    {
        if (filled($receipt->duplicate_of_id)) {
            return;
        }

        $original = null;

        if (filled($receipt->invoice_number)) {
            // Über die Rechnungsnummer (gleicher Lieferant bzw. gleicher Betrag).
            $original = Receipt::query()
                ->whereKeyNot($receipt->id)
                ->whereNull('duplicate_of_id')
                ->where('invoice_number', $receipt->invoice_number)
                ->when(
                    filled($receipt->supplier_id),
                    fn ($q) => $q->where('supplier_id', $receipt->supplier_id),
                    fn ($q) => $q->where('gross_amount', $receipt->gross_amount),
                )
                ->orderBy('id')
                ->first();
        } elseif (filled($receipt->supplier_id) && (float) $receipt->gross_amount > 0) {
            // Ohne erkannte Rechnungsnummer: gleicher Lieferant + identischer
            // Bruttobetrag ist ein starker Dubletten-Verdacht. Haben beide ein
            // Rechnungsdatum, muss es übereinstimmen.
            $original = Receipt::query()
                ->whereKeyNot($receipt->id)
                ->whereNull('duplicate_of_id')
                ->where('supplier_id', $receipt->supplier_id)
                ->where('gross_amount', $receipt->gross_amount)
                ->when(filled($receipt->invoice_date), fn ($q) => $q->where(function ($q) use ($receipt) {
                    $q->whereNull('invoice_date')
                        ->orWhereDate('invoice_date', $receipt->invoice_date->toDateString());
                }))
                ->orderBy('id')
                ->first();
        }

        if ($original) {
            $receipt->duplicate_of_id = $original->id;

            \Illuminate\Support\Facades\Log::info('Beleg als mögliche Dublette isoliert', [
                'receipt_id' => $receipt->id,
                'file' => $receipt->file_name,
                'invoice_number' => $receipt->invoice_number,
                'original_id' => $original->id,
                'original_file' => $original->file_name,
            ]);
        }
    }

    /**
     * Versucht, den Lieferanten zu erkennen: zuerst über die Kundennummer,
     * dann über USt-IdNr, dann über die IBAN. Nur bei eindeutigem Treffer.
     */
    private function matchSupplier(Receipt $receipt, string $text): void
    {
        $this->matchSupplierByCustomerNumber($receipt);
        if (filled($receipt->supplier_id)) {
            $this->matchBusinessByCustomerNumber($receipt);

            return;
        }

        // USt-IdNr
        $vat = $this->extractor->vatId($text);
        if ($vat) {
            $supplier = \App\Models\Supplier::query()
                ->whereRaw("UPPER(REPLACE(vat_id, ' ', '')) = ?", [$vat])
                ->first();
            if ($supplier) {
                $receipt->supplier_id = $supplier->id;
                $this->matchBusinessByCustomerNumber($receipt);

                return;
            }
        }

        // IBAN
        if (filled($receipt->iban)) {
            $supplier = \App\Models\Supplier::query()
                ->whereRaw("REPLACE(iban, ' ', '') = ?", [preg_replace('/\s+/', '', (string) $receipt->iban)])
                ->first();
            if ($supplier) {
                $receipt->supplier_id = $supplier->id;
                $this->matchBusinessByCustomerNumber($receipt);
            }
        }
    }

    /**
     * Bestimmt die Tankstelle über Lieferant + Kundennummer: jede Tankstelle
     * hat beim Lieferanten ihre eigene Kundennummer – ist der Lieferant bekannt
     * (egal wie erkannt), liefert die Kundennummer die Tankstelle.
     */
    private function matchBusinessByCustomerNumber(Receipt $receipt): void
    {
        if (filled($receipt->business_id) || blank($receipt->customer_number) || blank($receipt->supplier_id)) {
            return;
        }

        $businessIds = \App\Models\SupplierCustomerNumber::query()
            ->where('supplier_id', $receipt->supplier_id)
            ->where('customer_number', $receipt->customer_number)
            ->pluck('business_id')->filter()->unique();

        if ($businessIds->count() === 1) {
            $receipt->business_id = $businessIds->first();
        }
    }

    /**
     * Ordnet Lieferant + Tankstelle (Betrieb) anhand der erkannten Kundennummer
     * über die Verknüpfungstabelle zu – nur wenn eindeutig.
     */
    private function matchSupplierByCustomerNumber(Receipt $receipt): void
    {
        if (blank($receipt->customer_number) || filled($receipt->supplier_id)) {
            return;
        }

        $links = \App\Models\SupplierCustomerNumber::query()
            ->where('customer_number', $receipt->customer_number)
            ->get();

        // Eindeutiger Lieferant? Dann zuordnen (Tankstelle ebenfalls, falls eindeutig).
        $supplierIds = $links->pluck('supplier_id')->unique();
        if ($supplierIds->count() === 1) {
            $receipt->supplier_id = $supplierIds->first();

            $businessIds = $links->pluck('business_id')->unique();
            if (blank($receipt->business_id) && $businessIds->count() === 1) {
                $receipt->business_id = $businessIds->first();
            }
        }
    }
}
