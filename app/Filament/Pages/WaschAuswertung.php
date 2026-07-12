<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Services\Wash\WashAnalytics;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Controlling-Auswertung der Waschumsätze: Kennzahlen, Umsatzentwicklung,
 * Top-Programme, Umsatz je Kunde, Wochentagsstatistik – als Seite und PDF.
 */
class WaschAuswertung extends Page
{
    protected string $view = 'filament.pages.wasch-auswertung';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Waschanlage';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Auswertung';

    protected static ?string $navigationLabel = 'Auswertung';

    public int $filterYear = 2026;

    public int $filterStation = 0; // 0 = alle Stationen

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function mount(): void
    {
        $this->filterYear = (int) now()->year;
    }

    public function getBusinessesProperty(): Collection
    {
        return Business::orderBy('sort_order')->get();
    }

    /** Ausgewertete Kennzahlen (Karte + PayPal zusammen). */
    public function getDataProperty(): array
    {
        return (new WashAnalytics($this->filterYear, $this->filterStation ?: null))->build();
    }

    public function stationLabel(): string
    {
        if (! $this->filterStation) {
            return 'Alle Stationen';
        }

        return Business::find($this->filterStation)?->display_label ?? 'Station';
    }

    public function downloadPdf()
    {
        $pdf = DomPdf::loadView('pdf.wasch-auswertung', [
            'data' => $this->data,
            'year' => $this->filterYear,
            'stationLabel' => $this->stationLabel(),
            'generatedAt' => now()->format('d.m.Y'),
            'money' => fn ($v) => number_format((float) $v, 2, ',', '.') . ' €',
        ])->setPaper('a4');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'Wasch-Auswertung_' . $this->filterYear . '.pdf'
        );
    }
}
