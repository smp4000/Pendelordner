<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $total = $this->total;
        $pct = fn ($v) => $total > 0 ? round((float) $v / $total * 100) : 0;
        $blocks = [
            ['Kosten je Betrieb', $this->byBusiness],
            ['Kosten je Kostenstelle', $this->byCostCenter],
            ['Kosten je Kategorie', $this->byCategory],
            ['Kosten je Bankkonto', $this->byBankAccount],
            ['Top-Lieferanten', $this->bySupplier],
        ];
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        {{-- Steuerung --}}
        <div style="display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem;">
            <div style="width:18rem;">
                <label style="display:block;font-size:.85rem;font-weight:500;margin-bottom:.25rem;">Zeitraum</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="period">
                        @foreach ($this->periodOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div style="text-align:right;">
                <div style="font-size:.85rem;opacity:.6;">Gesamtkosten</div>
                <div style="font-size:1.5rem;font-weight:700;color:#dc2626;">{{ $money($total) }}</div>
            </div>
        </div>

        {{-- Aufschlüsselungen --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start;">
            @foreach ($blocks as [$title, $rows])
                <x-filament::section style="padding:0;overflow:hidden;">
                    <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">{{ $title }}</div>
                    <div>
                        @forelse ($rows as $row)
                            <div style="padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.12);">
                                <div style="display:flex;justify-content:space-between;font-size:.9rem;gap:.5rem;">
                                    <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $row->label }}
                                        <span style="opacity:.5;font-size:.75rem;">({{ $row->anzahl }})</span></span>
                                    <span style="font-weight:500;white-space:nowrap;">{{ $money($row->total) }}</span>
                                </div>
                                <div style="margin-top:.3rem;height:6px;width:100%;background:rgba(120,120,120,.15);border-radius:9999px;overflow:hidden;">
                                    <div style="height:100%;border-radius:9999px;background:#10b981;width:{{ $pct($row->total) }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <p style="padding:1rem;font-size:.85rem;opacity:.6;">Keine Daten im Zeitraum.</p>
                        @endforelse
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
