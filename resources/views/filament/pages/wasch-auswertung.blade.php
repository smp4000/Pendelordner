<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $d = $this->data;
        $k = $d['kpi'];
        $monate = [1 => 'Jan', 2 => 'Feb', 3 => 'Mär', 4 => 'Apr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Dez'];
        $wtage = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
        $maxMonth = max(1, max($d['byMonth']));
        $maxWd = max(1, max(array_map(fn ($x) => $x['brutto'], $d['weekday'])));
        $maxProg = max(1, max(array_map(fn ($x) => $x['count'], $d['programs'] ?: [['count' => 1]])));
        $maxCust = max(1, max(array_map(fn ($x) => $x['brutto'], $d['customers'] ?: [['brutto' => 1]])));
        $bar = fn ($pct, $color = '#059669') => '<div style="background:rgba(120,120,120,.12);border-radius:.25rem;height:.85rem;flex:1;overflow:hidden;"><div style="width:' . max(1, $pct) . '%;background:' . $color . ';height:100%;border-radius:.25rem;"></div></div>';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Filter --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;">
            <span style="font-weight:600;">Jahr:</span>
            <input type="number" wire:model.live="filterYear" style="width:6rem;padding:.35rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;">
            <span style="font-weight:600;">Station:</span>
            <select wire:model.live="filterStation" style="padding:.35rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;">
                <option value="0">Alle Stationen</option>
                @foreach ($this->businesses as $b)
                    <option value="{{ $b->id }}">{{ $b->short_name ?: $b->display_label }}</option>
                @endforeach
            </select>
            <span style="flex:1;"></span>
            <x-filament::button wire:click="downloadPdf" color="gray" size="sm" icon="heroicon-o-arrow-down-tray">Controlling-PDF</x-filament::button>
        </div>

        {{-- KPI-Karten --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.75rem;">
            @php
                $cards = [
                    ['Umsatz (brutto)', $money($k['brutto']), '#059669'],
                    ['davon USt 19 %', $money($k['ust']), '#6b7280'],
                    ['Netto', $money($k['netto']), '#374151'],
                    ['Wäschen', number_format($k['count'], 0, ',', '.'), '#0ea5e9'],
                    ['Ø Bon', $money($k['avg']), '#8b5cf6'],
                    ['Kunden', number_format($k['kunden'], 0, ',', '.'), '#f59e0b'],
                    ['Gratis-Wäschen', number_format($k['gratis'], 0, ',', '.'), '#b45309'],
                ];
            @endphp
            @foreach ($cards as [$label, $value, $color])
                <div style="border:1px solid rgba(120,120,120,.2);border-radius:.6rem;padding:.7rem .85rem;">
                    <div style="font-size:.75rem;opacity:.7;">{{ $label }}</div>
                    <div style="font-size:1.25rem;font-weight:700;color:{{ $color }};">{{ $value }}</div>
                </div>
            @endforeach
        </div>

        {{-- Umsatzentwicklung --}}
        <x-filament::section>
            <x-slot name="heading">📈 Umsatzentwicklung {{ $filterYear }}</x-slot>
            <div style="display:flex;flex-direction:column;gap:.3rem;">
                @foreach ($monate as $m => $lbl)
                    <div style="display:flex;align-items:center;gap:.6rem;font-size:.82rem;">
                        <span style="width:2.5rem;opacity:.7;">{{ $lbl }}</span>
                        {!! $bar(($d['byMonth'][$m] / $maxMonth) * 100) !!}
                        <span style="width:6rem;text-align:right;">{{ $money($d['byMonth'][$m]) }}</span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.25rem;">
            {{-- Top Programme --}}
            <x-filament::section>
                <x-slot name="heading">🚗 Meistverkaufte Waschprogramme</x-slot>
                <div style="display:flex;flex-direction:column;gap:.35rem;">
                    @forelse ($d['programs'] as $p)
                        <div style="display:flex;align-items:center;gap:.6rem;font-size:.82rem;">
                            <span style="width:6rem;">{{ $p['program'] }}</span>
                            {!! $bar(($p['count'] / $maxProg) * 100, '#0ea5e9') !!}
                            <span style="width:3rem;text-align:right;font-weight:600;">{{ $p['count'] }}×</span>
                            <span style="width:6rem;text-align:right;opacity:.7;">{{ $money($p['brutto']) }}</span>
                        </div>
                    @empty
                        <span style="opacity:.5;">Keine Daten.</span>
                    @endforelse
                </div>
            </x-filament::section>

            {{-- Wochentag --}}
            <x-filament::section>
                <x-slot name="heading">📅 Umsatz nach Wochentag</x-slot>
                <div style="display:flex;flex-direction:column;gap:.35rem;">
                    @foreach ($wtage as $wd => $lbl)
                        <div style="display:flex;align-items:center;gap:.6rem;font-size:.82rem;">
                            <span style="width:5.5rem;opacity:.7;">{{ $lbl }}</span>
                            {!! $bar(($d['weekday'][$wd]['brutto'] / $maxWd) * 100, '#8b5cf6') !!}
                            <span style="width:2.5rem;text-align:right;">{{ $d['weekday'][$wd]['count'] }}×</span>
                            <span style="width:6rem;text-align:right;opacity:.7;">{{ $money($d['weekday'][$wd]['brutto']) }}</span>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Top Kunden --}}
        <x-filament::section>
            <x-slot name="heading">👥 Umsatz je Kunde (Top 15)</x-slot>
            <div style="display:flex;flex-direction:column;gap:.35rem;">
                @forelse ($d['customers'] as $c)
                    <div style="display:flex;align-items:center;gap:.6rem;font-size:.82rem;">
                        <span style="width:12rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $c['name'] }}</span>
                        {!! $bar(($c['brutto'] / $maxCust) * 100, '#f59e0b') !!}
                        <span style="width:3rem;text-align:right;">{{ $c['count'] }}×</span>
                        <span style="width:6rem;text-align:right;font-weight:600;">{{ $money($c['brutto']) }}</span>
                    </div>
                @empty
                    <span style="opacity:.5;">Keine Daten.</span>
                @endforelse
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
