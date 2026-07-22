<?php

namespace App\Services\Wash;

use App\Models\WashPayment;
use App\Models\WashPaymentState;

/**
 * Kennzahlen und Auswertungen der Waschumsätze (Karte + PayPal zusammen).
 * Basis: bezahlte Wäschen (total > 0) mit gültigem State im gewählten Jahr,
 * optional je Station.
 */
class WashAnalytics
{
    public function __construct(public int $year, public ?int $businessId = null) {}

    /** @return array<string, mixed> */
    public function build(): array
    {
        $excluded = WashPaymentState::where('counts_as_revenue', false)->pluck('code')->all();

        // whereBetween statt whereYear() – so kann der Index auf payment_date
        // genutzt werden (YEAR(spalte) verhindert jede Indexnutzung).
        $from = \Illuminate\Support\Carbon::create($this->year, 1, 1)->toDateString();
        $to = \Illuminate\Support\Carbon::create($this->year, 12, 31)->toDateString();

        $base = WashPayment::query()
            ->whereBetween('payment_date', [$from, $to])
            ->when($this->businessId, fn ($q) => $q->where('business_id', $this->businessId))
            ->when($excluded, fn ($q) => $q->whereNotIn('state_code', $excluded));

        /** @var \Illuminate\Support\Collection<int, WashPayment> $paid */
        $paid = (clone $base)->where('total', '>', 0)
            ->get(['payment_date', 'total', 'tax', 'program', 'customer_name']);

        $brutto = round((float) $paid->sum('total'), 2);
        $ust = round((float) $paid->sum('tax'), 2);
        $count = $paid->count();

        $kpi = [
            'brutto' => $brutto,
            'ust' => $ust,
            'netto' => round($brutto - $ust, 2),
            'count' => $count,
            'avg' => $count ? round($brutto / $count, 2) : 0.0,
            'kunden' => $paid->pluck('customer_name')->filter()->unique()->count(),
            'gratis' => (clone $base)->where('is_free', true)->count(),
        ];

        // Umsatz je Monat.
        $byMonth = array_fill(1, 12, 0.0);
        foreach ($paid as $p) {
            $m = (int) $p->payment_date->format('n');
            $byMonth[$m] = round($byMonth[$m] + (float) $p->total, 2);
        }

        // Meistverkaufte Programme (nach Anzahl).
        $programs = $paid->groupBy(fn ($p) => $p->program ?: '—')
            ->map(fn ($g, $k) => ['program' => $k, 'count' => $g->count(), 'brutto' => round((float) $g->sum('total'), 2)])
            ->sortByDesc('count')->values()->all();

        // Umsatz je Kunde (Top 15 nach Umsatz).
        $customers = $paid->groupBy(fn ($p) => $p->customer_name ?: '—')
            ->map(fn ($g, $k) => ['name' => $k, 'count' => $g->count(), 'brutto' => round((float) $g->sum('total'), 2)])
            ->sortByDesc('brutto')->take(15)->values()->all();

        // Wochentag-Statistik (Mo–So).
        $weekday = [];
        for ($d = 1; $d <= 7; $d++) {
            $weekday[$d] = ['count' => 0, 'brutto' => 0.0];
        }
        foreach ($paid as $p) {
            $d = (int) $p->payment_date->dayOfWeekIso;
            $weekday[$d]['count']++;
            $weekday[$d]['brutto'] = round($weekday[$d]['brutto'] + (float) $p->total, 2);
        }

        return compact('kpi', 'byMonth', 'programs', 'customers', 'weekday');
    }
}
