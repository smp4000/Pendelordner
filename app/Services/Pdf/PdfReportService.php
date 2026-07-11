<?php

namespace App\Services\Pdf;

use App\Models\BankAccount;
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
    public function generateMonthlyReport(Carbon $month, ?Business $business = null, ?BankAccount $account = null): string
    {
        return $this->generate($month->copy()->startOfMonth(), $month->copy()->endOfMonth(), $business, $account);
    }

    /**
     * Sammelt alle Daten des Berichts (Umsätze, Steuerbüro-Dateien, monatliche
     * Anhang-Bündel und die Nummerierung je Monat ab 1) – gemeinsame Basis für
     * das PDF und den Einzel-Beleg-Export.
     *
     * @return array<string, mixed>
     */
    private function collectData(Carbon $from, Carbon $to, ?Business $business, ?BankAccount $account): array
    {
        // Ist ein Konto gewählt, definiert es den Betrieb -> nur nach Konto
        // filtern (verhindert 0 Treffer, falls die business_id der Umsätze noch
        // nicht zum aktuell zugeordneten Betrieb des Kontos passt).
        $transactions = BankTransaction::query()
            ->with(['receipts', 'category', 'costCenter', 'ledgerAccount', 'supplier', 'bankAccount', 'accountAssignments.ledgerAccount'])
            ->when($account, fn ($q) => $q->where('bank_account_id', $account->id))
            ->when(! $account && $business, fn ($q) => $q->where('business_id', $business->id))
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            // Chronologisch wie im Kontoauszug: Buchungstag, dann Wertstellung
            // (Valuta) aufsteigend – innerhalb eines Buchungstags ordnet die Bank
            // nach dem Wertdatum (z. B. Buchung 15.06. mit Wert 13.06./14.06.
            // zuerst). Fehlt das Wertdatum, gilt der Buchungstag.
            ->orderBy('booking_date')
            ->orderByRaw('COALESCE(value_date, booking_date) asc')
            ->orderBy('id')
            ->get();

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));

        // Steuerbüro-Dateien (nur bei Konto-Auswahl) im Zeitraum.
        $steuerDocs = $account
            ? \App\Models\SteuerDocument::where('bank_account_id', $account->id)
                ->where('include_in_report', true)
                ->whereBetween('period', [$from->copy()->startOfMonth()->toDateString(), $to->copy()->endOfMonth()->toDateString()])
                ->orderBy('period')->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        // Je Monat bündeln: zuerst die Steuerbüro-Dateien, dann die Kontoauszug-Belege.
        $months = [];
        foreach ($steuerDocs as $doc) {
            if ($doc->file_path && $disk->exists($doc->file_path)) {
                $months[$doc->period->format('Y-m')]['docs'][] = $doc;
            }
        }
        foreach ($transactions as $transaction) {
            $key = $transaction->booking_date?->format('Y-m') ?? '0000-00';
            foreach ($transaction->receipts as $receipt) {
                if ($receipt->include_in_report && $receipt->file_path && $disk->exists($receipt->file_path)) {
                    $months[$key]['receipts'][] = $receipt;
                }
            }
        }
        ksort($months);

        // Nummerierung je Monat ab 1: erst Steuerbüro-Dateien, dann Belege.
        // Dieselbe Beleg-Nummer steht in der Umsatzliste und wird angehängt gestempelt.
        $receiptNumbers = [];
        $steuerNumbers = [];
        foreach ($months as $bucket) {
            $n = 0;
            foreach ($bucket['docs'] ?? [] as $doc) {
                $steuerNumbers[$doc->id] = ++$n;
            }
            foreach ($bucket['receipts'] ?? [] as $receipt) {
                $receiptNumbers[$receipt->id] = ++$n;
            }
        }

        return [
            'transactions' => $transactions,
            'business' => $business ?: $account?->business,
            'disk' => $disk,
            'steuerDocs' => $steuerDocs,
            'months' => $months,
            'receiptNumbers' => $receiptNumbers,
            'steuerNumbers' => $steuerNumbers,
        ];
    }

    /**
     * Alle nummerierten Anhänge des Berichts als Einzeldateien:
     * Name = Jahr-Monat-Nummer (z. B. "2026-06-01.pdf"), gleiche Nummern wie
     * im Bericht (je Monat ab 1, Steuerbüro-Dateien zuerst).
     *
     * @return list<array{name: string, absolute: string}>
     */
    public function attachmentFiles(Carbon $from, Carbon $to, ?Business $business = null, ?BankAccount $account = null): array
    {
        $d = $this->collectData($from, $to, $business, $account);

        $out = [];
        foreach ($d['months'] as $key => $bucket) {
            foreach ($bucket['docs'] ?? [] as $doc) {
                // Kategorie ans Dateiname anhängen, z. B. "2026-06-01_Kontoauszug".
                $raw = trim((string) $doc->category);
                $label = $raw !== '' ? '_' . $this->sanitizeFileName($raw) : '';
                $out[] = [
                    'name' => $key . '-' . str_pad((string) $d['steuerNumbers'][$doc->id], 2, '0', STR_PAD_LEFT)
                        . $label . '.' . $this->fileExtension($doc->file_path),
                    'absolute' => $d['disk']->path($doc->file_path),
                ];
            }
            foreach ($bucket['receipts'] ?? [] as $receipt) {
                // Beim Beleg die Rechnungsnummer bzw. den Lieferanten anhängen.
                $raw = trim((string) ($receipt->invoice_number ?: ($receipt->supplier?->name ?? '')));
                $label = $raw !== '' ? '_' . $this->sanitizeFileName($raw) : '';
                $out[] = [
                    'name' => $key . '-' . str_pad((string) $d['receiptNumbers'][$receipt->id], 2, '0', STR_PAD_LEFT)
                        . $label . '.' . $this->fileExtension($receipt->file_path),
                    'absolute' => $d['disk']->path($receipt->file_path),
                ];
            }
        }

        return $out;
    }

    /**
     * Zusätzlich zugeordnete Belege, die NICHT im Bericht ausgedruckt werden
     * (include_in_report = false). Für die vollständige Sicherung im ZIP, damit
     * kein zugeordneter Beleg verloren geht. Der Dateiname enthält Datum,
     * Empfänger und Rechnungsnummer/Beleg-Nr. zur Wiedererkennung; gruppiert je
     * Monat. Ein Beleg, der an mehreren Umsätzen hängt, wird nur einmal
     * aufgenommen.
     *
     * @return list<array{name: string, absolute: string}>
     */
    public function unprintedReceiptFiles(Carbon $from, Carbon $to, ?Business $business = null, ?BankAccount $account = null): array
    {
        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));

        $transactions = BankTransaction::query()
            ->with(['receipts'])
            ->when($account, fn ($q) => $q->where('bank_account_id', $account->id))
            ->when(! $account && $business, fn ($q) => $q->where('business_id', $business->id))
            ->whereBetween('booking_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('booking_date')->orderBy('id')
            ->get();

        $out = [];
        $seenReceipts = [];
        $usedNames = [];
        foreach ($transactions as $transaction) {
            $key = $transaction->booking_date?->format('Y-m') ?? '0000-00';
            foreach ($transaction->receipts as $receipt) {
                if ($receipt->include_in_report) {
                    continue; // bereits als nummerierter Anhang enthalten
                }
                if (! $receipt->file_path || ! $disk->exists($receipt->file_path)) {
                    continue;
                }
                if (isset($seenReceipts[$receipt->id])) {
                    continue; // gleicher Beleg an mehreren Umsätzen nur einmal
                }
                $seenReceipts[$receipt->id] = true;

                $label = $this->sanitizeFileName(
                    ($transaction->booking_date?->format('Y-m-d') ?? '')
                    . '_' . ($transaction->counterparty ?: 'Beleg')
                    . '_' . ($receipt->invoice_number ?: ('Nr' . $receipt->id))
                );

                $name = $key . '/' . $label . '.' . $this->fileExtension($receipt->file_path);
                if (isset($usedNames[$name])) {
                    $name = $key . '/' . $label . '_' . $receipt->id . '.' . $this->fileExtension($receipt->file_path);
                }
                $usedNames[$name] = true;

                $out[] = ['name' => $name, 'absolute' => $disk->path($receipt->file_path)];
            }
        }

        return $out;
    }

    /**
     * Steuerbüro-PDFs, die NICHT im Bericht ausgedruckt werden
     * (include_in_report = false). Sollen trotzdem in der ZIP-Sicherung landen,
     * damit keine hochgeladene Datei verloren geht. Gruppiert je Monat, Name mit
     * Kategorie und Original-Dateiname.
     *
     * @return list<array{name: string, absolute: string}>
     */
    public function unprintedSteuerDocumentFiles(Carbon $from, Carbon $to, ?BankAccount $account = null): array
    {
        if (! $account) {
            return [];
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));

        $docs = \App\Models\SteuerDocument::where('bank_account_id', $account->id)
            ->where('include_in_report', false)
            ->whereBetween('period', [$from->copy()->startOfMonth()->toDateString(), $to->copy()->endOfMonth()->toDateString()])
            ->orderBy('period')->orderBy('sort_order')->orderBy('id')
            ->get();

        $out = [];
        $usedNames = [];
        foreach ($docs as $doc) {
            if (! $doc->file_path || ! $disk->exists($doc->file_path)) {
                continue;
            }
            // Unterordner = Kategorie (z. B. "Kundenrechnung"), darin die Datei
            // mit ihrem ORIGINALnamen (nicht dem zufälligen Speichernamen).
            $catFolder = $this->sanitizeFileName(trim((string) $doc->category) ?: 'Sonstige');
            $original = str_replace(['/', '\\'], '_', (string) ($doc->file_name ?: pathinfo((string) $doc->file_path, PATHINFO_BASENAME)));

            $name = $catFolder . '/' . $original;
            if (isset($usedNames[$name])) {
                // Namenskollision im selben Kategorie-Ordner: ID anhängen.
                $ext = $this->fileExtension($doc->file_path);
                $name = $catFolder . '/' . pathinfo($original, PATHINFO_FILENAME) . '_' . $doc->id . '.' . $ext;
            }
            $usedNames[$name] = true;

            $out[] = ['name' => $name, 'absolute' => $disk->path($doc->file_path)];
        }

        return $out;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}\-_ ]+/u', '', $name) ?? '';
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        return mb_substr($name !== '' ? $name : 'Beleg', 0, 80);
    }

    private function fileExtension(?string $path): string
    {
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'pdf';
    }

    /** Bericht für einen beliebigen Zeitraum ($withReceipts=false: nur Übersicht). */
    public function generate(Carbon $from, Carbon $to, ?Business $business = null, ?BankAccount $account = null, bool $withReceipts = true): string
    {
        $d = $this->collectData($from, $to, $business, $account);
        $transactions = $d['transactions'];
        $business = $d['business'];
        $months = $d['months'];
        $receiptNumbers = $d['receiptNumbers'];
        $steuerNumbers = $d['steuerNumbers'];
        $steuerDocs = $d['steuerDocs'];

        $pdf = new ReportPdf();

        $stats = $this->buildStats($transactions);
        $stats['appendedFiles'] = $withReceipts ? count($receiptNumbers) : 0;
        $stats['steuerFiles'] = $withReceipts ? count($steuerNumbers) : 0;

        // Hinweise an das Steuerbüro (Karten) für dieses Konto im Zeitraum.
        $reportNotes = $account
            ? \App\Models\ReportNote::with('lines')
                ->where('bank_account_id', $account->id)
                ->whereBetween('period', [$from->copy()->startOfMonth()->toDateString(), $to->copy()->endOfMonth()->toDateString()])
                ->orderBy('period')
                ->orderBy('sort_order')
                ->get()
            : collect();

        // 1.–3. Vorspann (Deckblatt, Zusammenfassung, Umsatzliste)
        $frontMatter = DomPdf::loadView('pdf.steuerberater', [
            'business' => $business,
            'account' => $account,
            'periodLabel' => $this->periodLabel($from, $to),
            'generatedAt' => now()->format('d.m.Y'),
            'transactions' => $transactions,
            'stats' => $stats,
            'receiptNumbers' => $receiptNumbers,
            'steuerNumbers' => $steuerNumbers,
            'steuerDocs' => $steuerDocs,
            'reportNotes' => $reportNotes,
            'money' => $this->money,
        ])->setPaper('a4')->output();
        // Kontoauszüge (Kategorie „Kontoauszug", mit Druck) kommen VOR die
        // Übersicht: der Steuerberater sieht zuerst den Originalauszug, danach
        // die Umsatzliste mit den Belegen.
        if ($withReceipts) {
            foreach ($months as $bucket) {
                foreach ($bucket['docs'] ?? [] as $doc) {
                    if ($this->isKontoauszug($doc)) {
                        $this->appendSteuerDocument($pdf, $doc, $steuerNumbers[$doc->id]);
                    }
                }
            }
        }

        $this->importPdfString($pdf, $frontMatter);

        // 4. Anhänge je Monat: erst die übrigen Steuerbüro-Dateien (Nr. 1…), dann
        //    Belege. Kontoauszüge sind schon vorne. Bei der reinen Übersicht
        //    ($withReceipts=false) entfallen die Anhänge.
        if ($withReceipts) {
            foreach ($months as $bucket) {
                foreach ($bucket['docs'] ?? [] as $doc) {
                    if ($this->isKontoauszug($doc)) {
                        continue; // bereits vor der Übersicht eingefügt
                    }
                    $this->appendSteuerDocument($pdf, $doc, $steuerNumbers[$doc->id]);
                }
                foreach ($bucket['receipts'] ?? [] as $receipt) {
                    $this->appendReceipt($pdf, $receipt, $receiptNumbers[$receipt->id]);
                }
            }
        }

        $name = ($withReceipts ? 'Pendelordner_' : 'Uebersicht_')
            . $from->format('Ymd') . '-' . $to->format('Ymd')
            . ($business ? '_b' . $business->id : '')
            . ($account ? '_k' . $account->id : '') . '.pdf';
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
        $this->appendFileByPath($pdf, $disk->path($receipt->file_path), (string) $receipt->mime_type, $number, 'Beleg');
    }

    /** Ist das Dokument ein Kontoauszug (kommt im Bericht vor die Übersicht)? */
    private function isKontoauszug(\App\Models\SteuerDocument $doc): bool
    {
        return str_contains(mb_strtolower((string) $doc->category), 'kontoauszug');
    }

    /** Hängt eine Steuerbüro-Datei an (gleiche Logik wie ein Beleg). */
    private function appendSteuerDocument(ReportPdf $pdf, \App\Models\SteuerDocument $doc, int $number): void
    {
        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        if (! $doc->file_path || ! $disk->exists($doc->file_path)) {
            return;
        }
        $this->appendFileByPath($pdf, $disk->path($doc->file_path), (string) $doc->mime_type, $number, 'Dokument');
    }

    /** Hängt eine Datei (PDF-Seiten oder Bild) mit aufgedruckter Nummer an. */
    private function appendFileByPath(ReportPdf $pdf, string $absolute, string $mime, ?int $number, string $kind = 'Beleg'): void
    {
        try {
            if ($mime === 'application/pdf' || str_ends_with(strtolower($absolute), '.pdf')) {
                try {
                    $this->importPdfString($pdf, (string) file_get_contents($absolute), $number);

                    return;
                } catch (Throwable $e) {
                    // Häufig PDF > 1.4 (komprimierte Objekt-Streams), die der freie
                    // FPDI-Parser nicht lesen kann -> auf PDF 1.4 konvertieren und
                    // erneut versuchen.
                    if ($converted = $this->convertPdfToCompatible($absolute)) {
                        try {
                            $this->importPdfString($pdf, (string) file_get_contents($converted), $number);
                            @unlink($converted);

                            return;
                        } catch (Throwable $e2) {
                            @unlink($converted);
                            report($e2);
                        }
                    } else {
                        report($e);
                    }
                }
            } elseif (in_array($mime, ['image/jpeg', 'image/png'], true)
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

        // Nicht einbettbar (z. B. TIFF oder nicht konvertierbares PDF) – Hinweisseite.
        // ASCII-Bindestrich, da der FPDF-Kernfont kein UTF-8 kann.
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 10, $kind . ' ' . ($number ?? '') . ' - Datei nicht einbettbar (' . $mime . ').');
    }

    /**
     * Konvertiert ein PDF nach PDF 1.4 (für den freien FPDI-Parser lesbar),
     * sofern qpdf oder Ghostscript auf dem Server verfügbar sind. Gibt den Pfad
     * einer Temp-Datei zurück oder null, wenn keine Konvertierung möglich war.
     */
    private function convertPdfToCompatible(string $absolute): ?string
    {
        if (! function_exists('exec') || ! function_exists('shell_exec')) {
            return null; // exec auf dem Hosting deaktiviert
        }

        $out = tempnam(sys_get_temp_dir(), 'rep_');
        if ($out === false) {
            return null;
        }

        // 1) qpdf: löst Objekt-Streams auf, behält Inhalt 1:1.
        if ($bin = $this->findBinary('qpdf')) {
            @exec(escapeshellarg($bin) . ' --object-streams=disable --stream-data=uncompress '
                . escapeshellarg($absolute) . ' ' . escapeshellarg($out) . ' 2>/dev/null', $o, $rc);
            if (is_file($out) && filesize($out) > 0) {
                return $out;
            }
        }

        // 2) Ghostscript: schreibt das PDF mit CompatibilityLevel 1.4 neu.
        if ($bin = $this->findBinary('gs')) {
            @exec(escapeshellarg($bin) . ' -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 '
                . '-sOutputFile=' . escapeshellarg($out) . ' ' . escapeshellarg($absolute) . ' 2>/dev/null', $o2, $rc2);
            if (is_file($out) && filesize($out) > 0) {
                return $out;
            }
        }

        @unlink($out);

        return null;
    }

    /** Sucht ein ausführbares Programm über "command -v"; null wenn nicht vorhanden. */
    private function findBinary(string $name): ?string
    {
        $path = @shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null');
        $path = is_string($path) ? trim($path) : '';

        return $path !== '' ? $path : null;
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
