<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.');
        $qty = fn ($v) => number_format((float) $v, 0, ',', '.');
        $inpTxt = 'width:100%;padding:.4rem .6rem;border:1px solid rgba(120,120,120,.3);border-radius:.5rem;background:transparent;';
        $monthNames = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
    @endphp

    {{-- Import --}}
    <x-filament::section>
        <x-slot name="heading">Kassenabrechnung importieren</x-slot>
        <x-slot name="description">Aral Back-Office „Zeitbezogene Abrechnung (Monat)" als CSV hochladen. Tankstelle und Monat werden automatisch aus der Datei erkannt (Stationsnummer + Abrechnungszeitraum).</x-slot>

        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;">
            <div style="flex:1;min-width:280px;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">CSV-Datei(en)</label>
                <input type="file" wire:model="posFiles" multiple accept=".csv,text/csv" style="{{ $inpTxt }}">
            </div>
            <div>
                <x-filament::button wire:click="importFiles" wire:loading.attr="disabled" wire:target="posFiles,importFiles" icon="heroicon-o-arrow-up-tray">Importieren</x-filament::button>
            </div>
        </div>
        <div wire:loading wire:target="posFiles" style="font-size:.8rem;opacity:.7;margin-top:.5rem;">Datei wird gelesen …</div>
    </x-filament::section>

    {{-- Auswahl --}}
    <x-filament::section>
        <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;">
            <div style="min-width:240px;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Tankstelle</label>
                <select wire:model.live="businessId" style="{{ $inpTxt }}">
                    @foreach ($this->businesses as $b)
                        <option value="{{ $b->id }}">{{ $b->display_label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:150px;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Monat</label>
                <select wire:model.live="month" style="{{ $inpTxt }}">
                    @foreach ($monthNames as $mn => $ml)
                        <option value="{{ $mn }}">{{ $ml }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width:120px;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Jahr</label>
                <select wire:model.live="year" style="{{ $inpTxt }}">
                    @for ($y = now()->year; $y >= now()->year - 6; $y--)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </x-filament::section>

    @php $sales = $this->sales; $s = $this->summary; @endphp

    @if ($sales->isNotEmpty())
        {{-- Kennzahlen --}}
        <x-filament::section>
            <x-slot name="heading">Ist-Erlöse {{ $monthNames[(int) $month] }} {{ $year }}</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                <div style="padding:1rem;border:1px solid rgba(125,125,125,.2);border-radius:.75rem;">
                    <div style="font-size:.8rem;opacity:.6;">Kraftstoff</div>
                    <div style="font-size:1.15rem;font-weight:700;">{{ $qty($s['fuel_liters']) }} L</div>
                    <div style="font-size:.78rem;opacity:.7;">Provision {{ $money($s['commission_ct']) }} ct/L</div>
                </div>
                <div style="padding:1rem;border:1px solid rgba(125,125,125,.2);border-radius:.75rem;">
                    <div style="font-size:.8rem;opacity:.6;">Kraftstoff-Provision</div>
                    <div style="font-size:1.15rem;font-weight:700;color:#059669;">{{ $money($s['provision']) }} €</div>
                </div>
                <div style="padding:1rem;border:1px solid rgba(125,125,125,.2);border-radius:.75rem;">
                    <div style="font-size:.8rem;opacity:.6;">Sonstige Erlöse (brutto)</div>
                    <div style="font-size:1.15rem;font-weight:700;">{{ $money($s['other_gross']) }} €</div>
                </div>
                <div style="padding:1rem;border:2px solid #059669;border-radius:.75rem;">
                    <div style="font-size:.8rem;opacity:.6;">Erlös gesamt</div>
                    <div style="font-size:1.15rem;font-weight:800;color:#059669;">{{ $money($s['total']) }} €</div>
                </div>
            </div>
            <p style="font-size:.78rem;opacity:.6;margin-top:.75rem;">Kraftstoff = Liter × Provision (je Tankstelle einstellbar unter Stammdaten → Betriebe). Sonstige Erlöse sind Bruttowerte laut Kasse.</p>
        </x-filament::section>

        {{-- Verbuchung: Erlöse je eDTAS-Konto --}}
        @php $bk = $this->booking; @endphp
        <x-filament::section>
            <x-slot name="heading">Verbuchung – Erlöse je eDTAS-Konto</x-slot>
            <x-slot name="description">Jede Warengruppe wird automatisch auf ihr eDTAS-Konto (EKW) gebucht; Netto/USt aus dem Bruttobetrag. Kraftstoff als Provision auf das Provisionskonto der Tankstelle.</x-slot>

            @if (! $bk['fuel_account'])
                <div style="margin-bottom:.75rem;padding:.5rem .7rem;border:1px solid #f59e0b;border-radius:.5rem;background:rgba(254,243,199,.4);font-size:.82rem;">
                    Kein <strong>eDTAS-Konto Kraftstoff-Provision</strong> hinterlegt – bitte beim Betrieb (Stammdaten → Betriebe) eintragen, sonst wird die Provision unter „—" geführt.
                </div>
            @endif

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.25);text-align:left;">
                            <th style="padding:.4rem .5rem;">eDTAS-Konto</th>
                            <th style="padding:.4rem .5rem;">Bezeichnung</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Netto</th>
                            <th style="padding:.4rem .5rem;text-align:right;">USt</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Brutto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bk['rows'] as $r)
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="padding:.3rem .5rem;font-weight:600;">{{ $r['account'] }}</td>
                                <td style="padding:.3rem .5rem;opacity:.8;">{{ $r['name'] ?? '—' }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;">{{ $money($r['net']) }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;opacity:.7;">{{ $money($r['ust']) }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;">{{ $money($r['brutto']) }}</td>
                            </tr>
                        @endforeach
                        <tr style="font-weight:700;border-top:2px solid rgba(120,120,120,.3);">
                            <td style="padding:.4rem .5rem;" colspan="2">Summe Erlöse</td>
                            <td style="padding:.4rem .5rem;text-align:right;color:#059669;">{{ $money($bk['sum']['net']) }}</td>
                            <td style="padding:.4rem .5rem;text-align:right;">{{ $money($bk['sum']['ust']) }}</td>
                            <td style="padding:.4rem .5rem;text-align:right;">{{ $money($bk['sum']['brutto']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">USt-Satz je Gruppe automatisch (7 % bei „erm."/„EM", 0 % bei Lotto/durchlaufend, sonst 19 %). Die Netto-Summe ist der Erlös für die GuV.</p>
        </x-filament::section>

        {{-- Artikelgruppen --}}
        <x-filament::section>
            <x-slot name="heading">Artikelgruppen (Kasse)</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.25);text-align:left;">
                            <th style="padding:.4rem .5rem;">Artikelgruppe</th>
                            <th style="padding:.4rem .5rem;">Konto</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Menge</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Betrag brutto</th>
                            <th style="padding:.4rem .5rem;text-align:right;">Erlös (rel.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $row)
                            @php $erloes = $row->is_fuel ? ((float) $row->quantity * $s['commission_ct'] / 100) : (float) $row->amount_gross; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="padding:.3rem .5rem;">{{ $row->article_group }}@if ($row->is_fuel)<span style="opacity:.5;font-size:.72rem;"> (Provision)</span>@endif</td>
                                <td style="padding:.3rem .5rem;opacity:.7;">{{ $row->ekw_konto }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;">{{ $qty($row->quantity) }}{{ $row->is_fuel ? ' L' : '' }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;">{{ $money($row->amount_gross) }}</td>
                                <td style="padding:.3rem .5rem;text-align:right;font-weight:600;">{{ $money($erloes) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="padding:1rem;text-align:center;opacity:.6;">Für diese Tankstelle und diesen Monat sind noch keine Kassenumsätze importiert.</div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
