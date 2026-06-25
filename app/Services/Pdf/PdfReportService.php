<?php

namespace App\Services\Pdf;

use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Erzeugt den Steuerberater-Pendelordner als PDF (Modul 12).
 *
 * Aufbau exakt in Bankumsatz-Reihenfolge:
 *   1. Deckblatt
 *   2. Zusammenfassung
 *   3. Chronologische Umsatzliste
 *   4. Je Umsatz mit Belegen: eine Trennseite, gefolgt von den
 *      Original-Belegdateien (PDF-Seiten importiert, Bilder eingebettet).
 *
 * DomPDF rendert die generierten Seiten, FPDI fügt die Original-Belege an.
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
            ->with(['receipts', 'category', 'costCenter', 'supplier', 'bankAccount'])
            ->when($business, fn ($q) => $q->where('business_id', $business->id))
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('booking_date')
            ->orderBy('id')
            ->get();

        $pdf = new Fpdi();

        $stats = $this->buildStats($transactions);
        $stats['appendedFiles'] = $this->countAppendableFiles($transactions);

        // 1.–3. Vorspann (Deckblatt, Zusammenfassung, Umsatzliste)
        $frontMatter = DomPdf::loadView('pdf.steuerberater', [
            'business' => $business,
            'periodLabel' => $this->periodLabel($from, $to),
            'generatedAt' => now()->format('d.m.Y'),
            'transactions' => $transactions,
            'stats' => $stats,
            'money' => $this->money,
        ])->setPaper('a4')->output();
        $this->importPdfString($pdf, $frontMatter);

        // 4. Je Umsatz mit Belegen: Trennseite + Original-Belege
        foreach ($transactions as $transaction) {
            if ($transaction->receipts->isEmpty()) {
                continue;
            }

            $divider = DomPdf::loadView('pdf.umsatz-divider', [
                't' => $transaction,
                'money' => $this->money,
            ])->setPaper('a4')->output();
            $this->importPdfString($pdf, $divider);

            foreach ($transaction->receipts as $receipt) {
                $this->appendReceipt($pdf, $receipt);
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
     * Zählt die tatsächlich anhängbaren Beleg-Dateien (Datei vorhanden).
     *
     * @param  \Illuminate\Support\Collection<int, BankTransaction>  $transactions
     */
    private function countAppendableFiles($transactions): int
    {
        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));

        return $transactions->sum(fn (BankTransaction $t) => $t->receipts
            ->filter(fn (Receipt $r) => $r->file_path && $disk->exists($r->file_path))
            ->count());
    }

    /** Importiert alle Seiten eines PDF-Strings in das Zieldokument. */
    private function importPdfString(Fpdi $pdf, string $pdfContent): void
    {
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($pdfContent));
        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }
    }

    /** Hängt einen Original-Beleg an: PDF-Seiten importieren, Bilder einbetten. */
    private function appendReceipt(Fpdi $pdf, Receipt $receipt): void
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
                $this->importPdfString($pdf, (string) file_get_contents($absolute));

                return;
            }

            if (in_array($mime, ['image/jpeg', 'image/png'], true)
                || preg_match('/\.(jpe?g|png)$/i', $absolute)) {
                $pdf->AddPage();
                // Bild auf A4-Breite (mit Rand) einpassen
                $pdf->Image($absolute, 10, 10, 190);

                return;
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Nicht einbettbar (z. B. TIFF) – Hinweisseite
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 10, 'Beleg ' . ($receipt->invoice_number ?: $receipt->id) . ' – Datei nicht einbettbar (' . $mime . ').');
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
