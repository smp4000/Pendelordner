<x-filament-panels::page>
    @php
        $years = $this->years;
        $rows = $this->rows;
        $ov = $this->overview;
        $money = fn ($v) => number_format((float) $v, 2, ',', '.');
        $inp = 'width:100%;min-width:90px;text-align:right;padding:.25rem .4rem;border:1px solid rgba(120,120,120,.3);border-radius:.375rem;background:transparent;font-size:.85rem;';
        $inpTxt = 'width:100%;padding:.3rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.375rem;background:transparent;';
        $th = 'padding:.35rem .5rem;text-align:right;font-size:.75rem;font-weight:600;opacity:.7;white-space:nowrap;';
        $tdL = 'padding:.2rem .5rem;font-size:.85rem;white-space:nowrap;';
        $revRows = collect($rows)->where('section', 'revenue');
        $costRows = collect($rows)->where('section', 'cost');
    @endphp

    {{-- Auswahl + Aktionen --}}
    <x-filament::section>
        <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="min-width:300px;flex:1;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Geschäftsplan</label>
                <select wire:model.live="planId" style="{{ $inpTxt }};text-align:left;">
                    <option value="">– Plan wählen –</option>
                    @foreach ($this->planOptions as $id => $t)
                        <option value="{{ $id }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                {{ $this->createPlanAction }}
                @if ($planId)
                    {{ $this->saveAction }}
                    {{ $this->deletePlanAction }}
                @endif
            </div>
        </div>
    </x-filament::section>

    @if ($planId && $years)
        {{-- Stammdaten --}}
        <x-filament::section>
            <x-slot name="heading">Stammdaten</x-slot>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;">
                <div><label style="font-size:.8rem;font-weight:600;">Titel</label>
                    <input type="text" wire:model.blur="stamm.title" style="{{ $inpTxt }}"></div>
                <div><label style="font-size:.8rem;font-weight:600;">TS-Name</label>
                    <input type="text" wire:model.blur="stamm.ts_name" style="{{ $inpTxt }}"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Adresse</label>
                    <input type="text" wire:model.blur="stamm.address" style="{{ $inpTxt }}"></div>
                <div><label style="font-size:.8rem;font-weight:600;">PLZ / Ort</label>
                    <input type="text" wire:model.blur="stamm.city" style="{{ $inpTxt }}"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Erstes Planjahr</label>
                    <input type="number" wire:model.live="stamm.year_from" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Letztes Planjahr</label>
                    <input type="number" wire:model.live="stamm.year_to" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Darlehensaufnahme (€)</label>
                    <input type="text" wire:model.blur="stamm.loan_amount" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Privatentnahme / Jahr (€)</label>
                    <input type="text" wire:model.blur="stamm.private_draw" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Anfangsbestand Liquidität (€)</label>
                    <input type="text" wire:model.blur="stamm.opening_balance" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">USt-Satz (%)</label>
                    <input type="text" wire:model.blur="stamm.vat_rate" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.8rem;font-weight:600;">Tilgung / Jahr (€)</label>
                    <input type="text" wire:model.blur="stamm.annual_repayment" style="{{ $inpTxt }};text-align:right;"></div>
            </div>
        </x-filament::section>

        {{-- Geschäftsplanübersicht (live) --}}
        <x-filament::section>
            <x-slot name="heading">Geschäftsplanübersicht</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;">Position</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}">{{ $y }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $lines = [
                                ['Umsatz gesamt', 'umsatz', false],
                                ['Rohertrag', 'rohertrag', false],
                                ['./. Kosten gesamt', 'kosten', false],
                                ['= Gewinn / Verlust', 'gewinn', true],
                            ];
                        @endphp
                        @foreach ($lines as [$lbl, $key, $bold])
                            <tr style="border-bottom:1px solid rgba(120,120,120,.15);{{ $bold ? 'font-weight:700;' : '' }}">
                                <td style="{{ $tdL }}">{{ $lbl }}</td>
                                @foreach ($years as $y)
                                    @php $val = $ov[$y][$key]; @endphp
                                    <td style="padding:.3rem .5rem;text-align:right;white-space:nowrap;{{ $key === 'gewinn' ? ($val < 0 ? 'color:#dc2626;' : 'color:#059669;') : '' }}">
                                        {{ $money($val) }} €
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="opacity:.65;font-size:.8rem;">
                            <td style="{{ $tdL }}">Rohertragsmarge</td>
                            @foreach ($years as $y)
                                <td style="padding:.2rem .5rem;text-align:right;">
                                    {{ $ov[$y]['umsatz'] > 0 ? $money($ov[$y]['rohertrag'] / $ov[$y]['umsatz'] * 100) . ' %' : '–' }}
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Umsatzplan --}}
        <x-filament::section>
            <x-slot name="heading">Umsatzplan</x-slot>
            <x-slot name="description">Umsatz (€) und BVD-Marge (%) je Jahr – der Rohertrag wird automatisch berechnet.</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;min-width:220px;">Bezeichnung</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}" colspan="3">{{ $y }} — Umsatz / BVD % / Rohertrag</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($revRows->groupBy('category') as $group => $grows)
                            <tr><td colspan="{{ 1 + count($years) * 3 }}" style="padding:.5rem .5rem .2rem;font-weight:700;font-size:.8rem;opacity:.8;">{{ $group }}</td></tr>
                            @foreach ($grows as $row)
                                @php $id = $row['id']; @endphp
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="{{ $tdL }}">{{ $row['label'] }}</td>
                                    @foreach ($years as $y)
                                        <td style="padding:.15rem .25rem;"><input type="text" wire:model.blur="rows.{{ $id }}.values.{{ $y }}.amount" style="{{ $inp }}"></td>
                                        <td style="padding:.15rem .25rem;"><input type="text" wire:model.blur="rows.{{ $id }}.values.{{ $y }}.margin" style="{{ $inp }};min-width:60px;"></td>
                                        <td style="padding:.15rem .35rem;text-align:right;font-size:.8rem;opacity:.7;white-space:nowrap;">{{ $money($this->rowRohertrag($row, $y)) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            <tr style="border-bottom:1px solid rgba(120,120,120,.25);font-size:.8rem;font-weight:600;opacity:.85;">
                                <td style="{{ $tdL }};text-align:right;">Summe {{ $group }}</td>
                                @foreach ($years as $y)
                                    @php $sU = $grows->sum(fn ($r) => (float) str_replace(['.', ','], ['', '.'], $r['values'][$y]['amount'] ?: '0')); @endphp
                                    @php $sR = $grows->sum(fn ($r) => $this->rowRohertrag($r, $y)); @endphp
                                    <td style="padding:.2rem .25rem;text-align:right;">{{ $money($sU) }}</td>
                                    <td></td>
                                    <td style="padding:.2rem .35rem;text-align:right;">{{ $money($sR) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Kostenplan --}}
        <x-filament::section>
            <x-slot name="heading">Kostenplan</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;min-width:220px;">Kostenart</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}">{{ $y }} (€)</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costRows as $row)
                            @php $id = $row['id']; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="{{ $tdL }}">{{ $row['label'] }}</td>
                                @foreach ($years as $y)
                                    <td style="padding:.15rem .25rem;"><input type="text" wire:model.blur="rows.{{ $id }}.values.{{ $y }}.amount" style="{{ $inp }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-weight:700;border-top:2px solid rgba(120,120,120,.3);">
                            <td style="{{ $tdL }};text-align:right;">Gesamtsumme Kosten</td>
                            @foreach ($years as $y)
                                <td style="padding:.3rem .25rem;text-align:right;">{{ $money($ov[$y]['kosten']) }} €</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        {{-- Liquiditätsplanung --}}
        @php
            $liq = $this->liquidity;
            $months = [1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
            $flow = [
                ['Einnahmen (inkl. USt)', 'einnahmen', ''],
                ['+ Darlehensaufnahme', 'darlehen', ''],
                ['./. Wareneinsatz (inkl. VSt)', 'ware', '-'],
                ['./. Personalkosten', 'personal', '-'],
                ['./. sonstige Kosten', 'sonstige', '-'],
                ['./. USt-Zahllast', 'ust', '-'],
                ['./. Tilgung', 'tilgung', '-'],
                ['./. Privatentnahme', 'privat', '-'],
            ];
        @endphp
        <x-filament::section>
            <x-slot name="heading">Liquiditätsplanung</x-slot>
            <x-slot name="description">Vereinfachtes Modell: gleichmäßige Verteilung auf 12 Monate, pauschaler USt-Satz, Vorsteuer auf den Wareneinsatz, USt-Zahllast monatlich. Anfangsbestand & Annahmen oben in den Stammdaten.</x-slot>
            @foreach ($years as $y)
                @php $L = $liq[$y]; @endphp
                <div style="margin-bottom:1.25rem;">
                    <div style="font-weight:700;margin-bottom:.35rem;">Jahr {{ $y }}</div>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.72rem;white-space:nowrap;">
                            <thead>
                                <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                                    <th style="{{ $th }};text-align:left;min-width:170px;">Position</th>
                                    @foreach ($months as $mn)
                                        <th style="{{ $th }}">{{ $mn }}</th>
                                    @endforeach
                                    <th style="{{ $th }};border-left:1px solid rgba(120,120,120,.3);">Summe</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($flow as [$lbl, $key, $sign])
                                    <tr style="border-bottom:1px solid rgba(120,120,120,.1);">
                                        <td style="{{ $tdL }};font-size:.72rem;">{{ $lbl }}</td>
                                        @foreach ($months as $m => $mn)
                                            <td style="padding:.15rem .35rem;text-align:right;">{{ $money($L['months'][$m][$key]) }}</td>
                                        @endforeach
                                        <td style="padding:.15rem .35rem;text-align:right;font-weight:600;border-left:1px solid rgba(120,120,120,.3);">{{ $money($L['totals'][$key]) }}</td>
                                    </tr>
                                @endforeach
                                <tr style="border-top:1px solid rgba(120,120,120,.3);font-weight:700;">
                                    <td style="{{ $tdL }}">= Saldo</td>
                                    @foreach ($months as $m => $mn)
                                        <td style="padding:.2rem .35rem;text-align:right;">{{ $money($L['months'][$m]['saldo']) }}</td>
                                    @endforeach
                                    <td style="padding:.2rem .35rem;text-align:right;border-left:1px solid rgba(120,120,120,.3);">{{ $money($L['totals']['saldo']) }}</td>
                                </tr>
                                <tr style="font-weight:700;">
                                    <td style="{{ $tdL }}">Stand Liquidität</td>
                                    @foreach ($months as $m => $mn)
                                        @php $st = $L['months'][$m]['stand']; @endphp
                                        <td style="padding:.2rem .35rem;text-align:right;{{ $st < 0 ? 'color:#dc2626;' : 'color:#059669;' }}">{{ $money($st) }}</td>
                                    @endforeach
                                    <td style="border-left:1px solid rgba(120,120,120,.3);"></td>
                                </tr>
                                <tr style="opacity:.7;">
                                    <td style="{{ $tdL }}">Stand Kredit</td>
                                    @foreach ($months as $m => $mn)
                                        <td style="padding:.15rem .35rem;text-align:right;">{{ $money($L['months'][$m]['kredit']) }}</td>
                                    @endforeach
                                    <td style="border-left:1px solid rgba(120,120,120,.3);"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </x-filament::section>
    @else
        <x-filament::section>
            <div style="padding:1rem;text-align:center;opacity:.6;">Bitte einen Plan wählen oder mit „Neuer Plan" anlegen.</div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
