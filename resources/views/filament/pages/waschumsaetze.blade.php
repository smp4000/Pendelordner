<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $monate = [0 => 'Alle Monate', 1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai',
            6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
        $kat = ['eigen' => 'Eigenfahrzeug', 'mitarbeiter' => 'Mitarbeiter', 'test' => 'Testwäsche'];
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Upload --}}
        <x-filament::section>
            <x-slot name="heading">Zahlungs-Export importieren</x-slot>
            <x-slot name="description">Karten- oder PayPal-Export hochladen. Fulda und Petersberg werden automatisch getrennt; Abos ohne Station ordnest du unten manuell zu.</x-slot>

            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;">
                <div style="display:flex;gap:.25rem;">
                    @foreach (['card' => 'Karte', 'paypal' => 'PayPal'] as $val => $lbl)
                        <button type="button" wire:click="$set('uploadMethod','{{ $val }}')"
                            style="padding:.4rem .9rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;cursor:pointer;font-size:.85rem;{{ $uploadMethod === $val ? 'background:#10b981;color:#fff;border-color:#10b981;' : 'background:transparent;' }}">{{ $lbl }}</button>
                    @endforeach
                </div>
                <input type="file" wire:model="uploadFile" accept=".csv,text/csv"
                    style="font-size:.85rem;">
                <x-filament::button wire:click="importUpload" wire:loading.attr="disabled" wire:target="importUpload,uploadFile" icon="heroicon-o-arrow-up-tray">
                    Importieren
                </x-filament::button>
                <span wire:loading wire:target="importUpload,uploadFile" style="font-size:.8rem;opacity:.7;">Verarbeite…</span>
            </div>
            @error('uploadFile') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
        </x-filament::section>

        {{-- Filter --}}
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;">
            <span style="font-weight:600;">Zeitraum:</span>
            <select wire:model.live="filterMonth" style="padding:.35rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;">
                @foreach ($monate as $m => $lbl)
                    <option value="{{ $m }}">{{ $lbl }}</option>
                @endforeach
            </select>
            <input type="number" wire:model.live="filterYear" style="width:6rem;padding:.35rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;">
            <span style="flex:1;"></span>
            <x-filament::button wire:click="downloadPdf" color="gray" size="sm" icon="heroicon-o-arrow-down-tray">
                Kassen-Liste als PDF
            </x-filament::button>
        </div>

        {{-- Kassen-Liste je Station --}}
        @forelse ($this->kassenListe as $block)
            <x-filament::section>
                <x-slot name="heading">Kassen-Liste · {{ $block['business']->display_label }}</x-slot>
                <x-slot name="description">Artikel wie angegeben in die Kasse buchen; die Korrekturzeile bringt die Summe auf den tatsächlichen Geldeingang.</x-slot>

                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid rgba(120,120,120,.25);">
                                <th style="padding:.4rem;">Menge</th>
                                <th style="padding:.4rem;">Artikel</th>
                                <th style="padding:.4rem;">EAN</th>
                                <th style="padding:.4rem;text-align:right;">Einzel (VK)</th>
                                <th style="padding:.4rem;text-align:right;">Gesamt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($block['lines'] as $line)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.4rem;font-weight:600;">{{ $line['qty'] }} ×</td>
                                    <td style="padding:.4rem;">{{ $line['name'] }}
                                        @if (! $line['ean'])<span style="color:#b45309;font-size:.72rem;"> · EAN fehlt</span>@endif
                                    </td>
                                    <td style="padding:.4rem;font-family:monospace;">{{ $line['ean'] ?: '—' }}</td>
                                    <td style="padding:.4rem;text-align:right;">{{ $line['vk'] !== null ? $money($line['vk']) : '—' }}</td>
                                    <td style="padding:.4rem;text-align:right;">{{ $money($line['zwischensumme']) }}</td>
                                </tr>
                            @endforeach
                            @if (abs($block['correction']) >= 0.005)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);color:#b45309;">
                                    <td style="padding:.4rem;"></td>
                                    <td style="padding:.4rem;" colspan="3">Preis-/Rabattkorrektur (auf Geldeingang)</td>
                                    <td style="padding:.4rem;text-align:right;">{{ $money($block['correction']) }}</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr style="border-top:2px solid rgba(120,120,120,.25);font-weight:700;">
                                <td style="padding:.4rem;" colspan="4">Summe = Geldeingang ({{ $block['count'] }} Wäschen)</td>
                                <td style="padding:.4rem;text-align:right;color:#059669;">{{ $money($block['sum_ist']) }}</td>
                            </tr>
                            <tr style="opacity:.75;">
                                <td style="padding:.2rem .4rem;" colspan="4">davon USt 19 %</td>
                                <td style="padding:.2rem .4rem;text-align:right;">{{ $money($block['ust']) }}</td>
                            </tr>
                            <tr style="opacity:.75;">
                                <td style="padding:.2rem .4rem;" colspan="4">Netto</td>
                                <td style="padding:.2rem .4rem;text-align:right;">{{ $money($block['net']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p style="opacity:.6;font-size:.9rem;">Keine bezahlten Wäschen im gewählten Zeitraum.</p>
            </x-filament::section>
        @endforelse

        {{-- Gratis-Doku --}}
        @if ($this->freeDoc->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Gratis-Wäschen (0 €) – nur zur Doku, kein Umsatz</x-slot>
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                        <thead>
                            <tr style="text-align:left;border-bottom:2px solid rgba(120,120,120,.25);">
                                <th style="padding:.4rem;">Datum</th><th style="padding:.4rem;">Station</th>
                                <th style="padding:.4rem;">Programm</th><th style="padding:.4rem;">Kennzeichen</th>
                                <th style="padding:.4rem;">Kategorie</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->freeDoc as $f)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.4rem;">{{ $f->payment_date?->format('d.m.Y') }}</td>
                                    <td style="padding:.4rem;">{{ $f->business?->short_name ?: ($f->is_subscription ? 'Abo (offen)' : '—') }}</td>
                                    <td style="padding:.4rem;">{{ $f->program ?: '—' }}</td>
                                    <td style="padding:.4rem;">{{ $f->plate ?: '—' }}</td>
                                    <td style="padding:.4rem;">
                                        @php $c = $f->free_category; @endphp
                                        @if ($c)
                                            <span style="padding:.1rem .45rem;border-radius:.3rem;font-size:.75rem;background:{{ $c === 'mitarbeiter' ? 'rgba(59,130,246,.15);color:#1d4ed8' : ($c === 'test' ? 'rgba(120,120,120,.15)' : 'rgba(16,185,129,.15);color:#059669') }};">{{ $kat[$c] ?? $c }}</span>
                                        @else
                                            <span style="color:#b45309;font-size:.75rem;">unklassifiziert</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- Alle Zahlungen + manuelle Abo-Zuordnung --}}
        <x-filament::section>
            <x-slot name="heading">Zahlungen ({{ $this->payments->count() }})</x-slot>
            <x-slot name="description">Abos ohne Station bitte hier einer Tankstelle zuordnen.</x-slot>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid rgba(120,120,120,.25);">
                            <th style="padding:.4rem;">Datum</th><th style="padding:.4rem;">Zahlart</th>
                            <th style="padding:.4rem;">Kunde</th><th style="padding:.4rem;">Programm</th>
                            <th style="padding:.4rem;">Kennzeichen</th><th style="padding:.4rem;text-align:right;">Betrag</th>
                            <th style="padding:.4rem;">State</th><th style="padding:.4rem;">Station</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->payments as $p)
                            <tr style="border-bottom:1px solid rgba(120,120,120,.1);{{ $p->is_free ? 'opacity:.7;' : '' }}">
                                <td style="padding:.35rem .4rem;">{{ $p->payment_date?->format('d.m.Y') }}</td>
                                <td style="padding:.35rem .4rem;">{{ strtoupper($p->payment_method) }}</td>
                                <td style="padding:.35rem .4rem;">{{ $p->customer_name ?: '—' }}</td>
                                <td style="padding:.35rem .4rem;">{{ $p->program ?: '—' }}@if ($p->is_subscription) <span style="font-size:.7rem;opacity:.6;">(Abo)</span>@endif</td>
                                <td style="padding:.35rem .4rem;">{{ $p->plate ?: '—' }}</td>
                                <td style="padding:.35rem .4rem;text-align:right;{{ $p->is_free ? '' : 'color:#059669;' }}">{{ $money($p->total) }}</td>
                                <td style="padding:.35rem .4rem;">{{ $p->state_code }}</td>
                                <td style="padding:.35rem .4rem;">
                                    <select wire:change="assignStation({{ $p->id }}, $event.target.value)"
                                        style="padding:.2rem .4rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;font-size:.78rem;{{ $p->business_id ? '' : 'border-color:#f59e0b;background:rgba(245,158,11,.08);' }}">
                                        <option value="">– zuordnen –</option>
                                        @foreach ($this->businesses as $b)
                                            <option value="{{ $b->id }}" @selected($p->business_id === $b->id)>{{ $b->short_name ?: $b->display_label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
