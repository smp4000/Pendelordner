<?php

namespace App\Services\Pdf;

use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Erzeugt den Steuerberater-Pendelordner als PDF (Modul 12).
 *
 * Aufbau exakt in Bankumsatz-Reihenfolge:
 *   1. Deckblatt
 *   2. Zusammenfassung
 *   3. Chronologische Umsatzliste (mit fortlaufender Beleg-Nummer)
 *   4. Die Original-Belegdateien chronologisch angehängt (PDF-Seiten
 *      importiert, Bilder eingebettet), jeweils mit aufgedruckter
 *      Beleg-Nummer oben rechts.
 *
 * DomPDF rendert die generierten Seiten, FPDI fügt die Original-Belege an.
 * Der Beleg-Stempel wird nur in den Bericht gezeichnet; die gespeicherte
 * Original-Belegdatei bleibt unverändert.
 */
class PdfReportService
{
    /** Geldformat de-DE. */
    private \Closure $money;

    public function __construct()
    {
        $this->money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
    }

    /**
     * Erzeugt den Monatsbericht und legt ihn auf dem local-Disk ab.
     * Gibt den relativen Pfad zurück.
     */
    /** Bericht für einen ganzen Monat (Komfort-Wrapper). */
    public function generateMonthlyReport(Carbon $month, ?Business $business = null): string
    {
        return $this->generate($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $business);
    }

    /** Bericht für einen beliebigen Zeitraum. */
    public function generate(Carbon $from, Carbon $to, ?Business $business = null): string
    {
        $transactions = BankTransaction::query()
            ->with(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount'])
            ->when($business, fn ($q) => $q->where('business_id', $business->id))
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('booking_date')
            ->orderBy('id')
            ->get();

        $pdf = new ReportPdf();

        $stats = $this->buildStats($transactions);

        // Fortlaufende Beleg-Nummern (chronologisch) vergeben: Umsatz->id => [receipt_id => Nr].
        // Dieselbe Nummer steht in der Umsatzliste und wird auf den angehängten Beleg gestempelt.
        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $receiptNumbers = [];
        $counter = 0;
        foreach ($transactions as $transaction) {
            foreach ($transaction->receipts as $receipt) {
                if ($receipt->include_in_report
                    && $receipt->file_path && $disk->exists($receipt->file_path)) {
                    $receiptNumbers[$receipt->id] = ++$counter;
                }
            }
        }

        // Tatsächlich angehängte Belege = Anzahl vergebener Beleg-Nummern.
        $stats['appendedFiles'] = count($receiptNumbers);

        // 1.–3. Vorspann (Deckblatt, Zusammenfassung, Umsatzliste)
        $frontMatter = DomPdf::loadView('pdf.steuerberater', [
            'business' => $business,
            'periodLabel' => $this->periodLabel($from, $to),
            'generatedAt' => now()->format('d.m.Y'),
            'transactions' => $transactions,
            'stats' => $stats,
            'receiptNumbers' => $receiptNumbers,
            'money' => $this->money,
        ])->setPaper('a4')->output();
        $this->importPdfString($pdf, $frontMatter);

        // 4. Belege chronologisch anhängen – ohne Trennseite, mit aufgedruckter Beleg-Nummer.
        foreach ($transactions as $transaction) {
            foreach ($transaction->receipts as $receipt) {
                if (isset($receiptNumbers[$receipt->id])) {
                    $this->appendReceipt($pdf, $receipt, $receiptNumbers[$receipt->id]);
                }
            }
        }

        $name = 'Pendelordner_' . $from->format('Ymd') . '-' . $to->format('Ymd') . ($business ? '_' . $business->id : '') . '.pdf';
        $path = 'reports/' . $name;
        Storage::disk('local')->put($path, $pdf->Output('S'));

        return $path;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BankTransaction>  $transactions
     * @return array<string, mixed>
     */
    private function buildStats($transactions): array
    {
        return [
            'count' => $transactions->count(),
            'income' => $transactions->where('amount', '>', 0)->sum('amount'),
            'expense' => $transactions->where('amount', '<', 0)->sum('amount'),
            'receipts' => $transactions->sum(fn (BankTransaction $t) => $t->receipts->count()),
            'withoutReceipt' => $transactions->filter(fn (BankTransaction $t) => $t->receipts->isEmpty())->count(),
            'unreviewed' => $transactions->where('reviewed', false)->count(),
        ];
    }

    /**
     * Importiert alle Seiten eines PDF-Strings in das Zieldokument.
     * Ist $stampNumber gesetzt, wird die Beleg-Nummer auf die erste Seite gedruckt.
     */
    private function importPdfString(ReportPdf $pdf, string $pdfContent, ?int $stampNumber = null): void
    {
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdfContent));
        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($page === 1 && $stampNumber !== null) {
                $this->stampNumber($pdf, $stampNumber, (float) $size['width']);
            }
        }
    }

    /**
     * Druckt eine dezente, abgerundete Beleg-Nummer oben rechts auf die Seite.
     * Wird nur in den Bericht gezeichnet – die Originaldatei bleibt unverändert.
     */
    private function stampNumber(ReportPdf $pdf, int $number, float $pageWidth): void
    {
        $label = 'Beleg ' . $number;

        $pdf->SetFont('Helvetica', 'B', 8);
        $padX = 2.6;            // horizontaler Innenabstand
        $h = 5.0;              // Höhe der Pille
        $w = $pdf->GetStringWidth($label) + 2 * $padX;
        $margin = 5.0;
        $x = $pageWidth - $w - $margin;
        $y = $margin;

        // Abgerundete Pille (volle Rundung), dezentes Grün
        $pdf->SetFillColor(5, 150, 105);
        $pdf->roundedRect($x, $y, $w, $h, $h / 2, 'F');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, $label, 0, 0, 'C');

        // Zustand zurücksetzen, damit folgende Inhalte nicht beeinflusst werden
        $pdf->SetTextColor(0, 0, 0);
    }

    /** Hängt einen Original-Beleg an: PDF-Seiten importieren, Bilder einbetten. */
    private function appendReceipt(ReportPdf $pdf, Receipt $receipt, ?int $number = null): void
    {
        if (! $receipt->file_path) {
            return;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        if (! $disk->exists($receipt->file_path)) {
            return;
        }

        $absolute = $disk->path($receipt->file_path);
        $mime = (string) $receipt->mime_type;

        try {
            if ($mime === 'application/pdf' || str_ends_with(strtolower($absolute), '.pdf')) {
                $this->importPdfString($pdf, (string) file_get_contents($absolute), $number);

                return;
            }

            if (in_array($mime, ['image/jpeg', 'image/png'], true)
                || preg_match('/\.(jpe?g|png)$/i', $absolute)) {
                $pdf->AddPage();
                // Bild auf A4-Breite (mit Rand) einpassen
                $pdf->Image($absolute, 10, 10, 190);
                if ($number !== null) {
                    $this->stampNumber($pdf, $number, 210.0); // A4-Breite in mm
                }

                return;
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Nicht einbettbar (z. B. TIFF) – Hinweisseite
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 10, 'Beleg ' . ($number ?? $receipt->invoice_number ?: $receipt->id) . ' – Datei nicht einbettbar (' . $mime . ').');
    }

    /** Lesbares Zeitraum-Label: ganzer Monat -> "Juni 2026", sonst "01.06.2026 – 30.06.2026". */
    private function periodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($from->copy()->startOfMonth())
            && $to->isSameDay($from->copy()->endOfMonth())) {
            return $this->germanMonth($from);
        }

        return $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y');
    }

    private function germanMonth(Carbon $month): string
    {
        $names = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return $names[$month->month] . ' ' . $month->year;
    }
}
