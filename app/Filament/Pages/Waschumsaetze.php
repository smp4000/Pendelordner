<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\WashArticle;
use App\Models\WashPayment;
use App\Models\WashPaymentState;
use App\Services\Wash\WashPaymentImporter;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Waschumsätze (Karte/PayPal): Import, Liste, manuelle Abo-Zuordnung und die
 * Monats-Kassen-Liste je Station zum Nachbuchen in der Kasse.
 */
class Waschumsaetze extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.waschumsaetze';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Waschanlage';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Waschumsätze';

    protected static ?string $navigationLabel = 'Waschumsätze';

    /** Hochzuladende Export-Datei. */
    public mixed $uploadFile = null;

    /** Zahlart des Uploads: card | paypal. */
    public string $uploadMethod = 'card';

    // Filter für Liste + Kassen-Liste.
    public int $filterYear = 2026;

    public int $filterMonth = 0; // 0 = alle Monate

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->filterYear = (int) now()->year;
        $this->filterMonth = (int) now()->month;
    }

    /** Export-Datei importieren (Station wird automatisch getrennt). */
    public function importUpload(): void
    {
        $this->validate(['uploadFile' => 'required|file'], [], ['uploadFile' => 'Datei']);

        try {
            $content = $this->uploadFile->get();
            $stats = (new WashPaymentImporter())->import($content, $this->uploadMethod);
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Import fehlgeschlagen')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->reset('uploadFile');

        $stationInfo = collect($stats['byBusiness'] ?? [])
            ->map(fn ($n, $name) => "$name: $n")->implode(', ');

        Notification::make()
            ->title($stats['imported'] . ' Zahlung(en) importiert (' . strtoupper($this->uploadMethod) . ')')
            ->body(trim(
                ($stationInfo ? $stationInfo . '. ' : '')
                . ($stats['unassigned'] > 0 ? $stats['unassigned'] . ' Abo(s) ohne Station – bitte unten zuordnen. ' : '')
                . ($stats['skipped'] > 0 ? $stats['skipped'] . ' Dublette(n) übersprungen.' : '')
            ) ?: null)
            ->success()->send();
    }

    /** Ein (Abo-)Umsatz einer Station manuell zuordnen. */
    public function assignStation(int $paymentId, ?int $businessId): void
    {
        WashPayment::whereKey($paymentId)->update(['business_id' => $businessId ?: null]);
    }

    /** Beschriftung des gewählten Zeitraums. */
    public function periodLabel(): string
    {
        $monate = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
            7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

        return ($this->filterMonth >= 1 && $this->filterMonth <= 12 ? $monate[$this->filterMonth] . ' ' : 'Jahr ')
            . $this->filterYear;
    }

    /** Kassen-Liste + Auswertung als PDF herunterladen. */
    public function downloadPdf()
    {
        $pdf = DomPdf::loadView('pdf.waschumsaetze', [
            'kassenListe' => $this->kassenListe,
            'freeDoc' => $this->freeDoc,
            'periodLabel' => $this->periodLabel(),
            'generatedAt' => now()->format('d.m.Y'),
            'money' => fn ($v) => number_format((float) $v, 2, ',', '.') . ' €',
        ])->setPaper('a4');

        $name = 'Waschumsaetze_' . $this->filterYear
            . ($this->filterMonth >= 1 && $this->filterMonth <= 12 ? '-' . str_pad((string) $this->filterMonth, 2, '0', STR_PAD_LEFT) : '')
            . '.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $name);
    }

    /** Betriebe für Filter/Zuordnung. */
    public function getBusinessesProperty(): Collection
    {
        return Business::orderBy('sort_order')->get();
    }

    /** Gefilterte Zahlungsliste (nach Zeitraum). */
    public function getPaymentsProperty(): Collection
    {
        return WashPayment::query()
            ->with('business')
            ->when($this->filterYear, fn ($q) => $q->whereYear('payment_date', $this->filterYear))
            ->when($this->filterMonth >= 1 && $this->filterMonth <= 12,
                fn ($q) => $q->whereMonth('payment_date', $this->filterMonth))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->limit(1000)
            ->get();
    }

    /** State-Codes, die NICHT als Umsatz zählen (Storno/Erstattung). */
    private function excludedStateCodes(): array
    {
        return WashPaymentState::where('counts_as_revenue', false)->pluck('code')->all();
    }

    /**
     * Kassen-Liste je Station für den gewählten Zeitraum: bezahlte Wäschen
     * (total > 0, gültiger State) je Programm/Artikel zusammengefasst; eine
     * Korrekturzeile bringt die Summe auf den tatsächlichen Geldeingang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKassenListeProperty(): array
    {
        $excluded = $this->excludedStateCodes();

        $out = [];
        foreach ($this->businesses as $business) {
            $rows = WashPayment::query()
                ->where('business_id', $business->id)
                ->where('total', '>', 0)
                ->when($this->filterYear, fn ($q) => $q->whereYear('payment_date', $this->filterYear))
                ->when($this->filterMonth >= 1 && $this->filterMonth <= 12,
                    fn ($q) => $q->whereMonth('payment_date', $this->filterMonth))
                ->when($excluded, fn ($q) => $q->whereNotIn('state_code', $excluded))
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $articles = WashArticle::where('business_id', $business->id)->get()->keyBy('program');

            $lines = [];
            foreach ($rows->groupBy('program') as $program => $group) {
                $article = $articles->get((string) $program);
                $vk = $article?->price !== null ? (float) $article->price : null;
                $qty = $group->count();
                $ist = round((float) $group->sum('total'), 2);
                $lines[] = [
                    'program' => $program ?: '—',
                    'name' => $article?->name ?: ($program ?: 'Unbekannt'),
                    'ean' => $article?->ean,
                    'qty' => $qty,
                    'vk' => $vk,
                    'zwischensumme' => $vk !== null ? round($vk * $qty, 2) : $ist,
                    'ist' => $ist,
                ];
            }

            usort($lines, fn ($a, $b) => strcmp($a['name'], $b['name']));

            $sumZwischen = round(array_sum(array_column($lines, 'zwischensumme')), 2);
            $sumIst = round($rows->sum('total'), 2);
            $ust = round($rows->sum('tax'), 2);

            $out[] = [
                'business' => $business,
                'lines' => $lines,
                'sum_zwischen' => $sumZwischen,
                'sum_ist' => $sumIst,
                'correction' => round($sumIst - $sumZwischen, 2),
                'ust' => $ust,
                'net' => round($sumIst - $ust, 2),
                'count' => $rows->count(),
            ];
        }

        return $out;
    }

    /**
     * Gratis-Wäschen (0 €) im Zeitraum – nur Doku, kein Umsatz. Mit Kategorie
     * (eigen/mitarbeiter/test) über das Kennzeichen.
     */
    public function getFreeDocProperty(): Collection
    {
        return WashPayment::query()
            ->with('business')
            ->where('is_free', true)
            ->when($this->filterYear, fn ($q) => $q->whereYear('payment_date', $this->filterYear))
            ->when($this->filterMonth >= 1 && $this->filterMonth <= 12,
                fn ($q) => $q->whereMonth('payment_date', $this->filterMonth))
            ->orderByDesc('payment_date')
            ->get();
    }
}
