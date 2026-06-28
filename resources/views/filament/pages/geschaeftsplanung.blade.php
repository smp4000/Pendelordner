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
        <style>
            [x-cloak]{display:none!important}
            .gp-tabs{display:flex;flex-wrap:wrap;gap:.25rem;padding:.35rem;background:rgba(125,125,125,.12);border-radius:.75rem;margin-bottom:1.25rem;}
            .gp-tab{padding:.5rem 1.05rem;border:0;border-radius:.55rem;background:transparent;color:inherit;opacity:.6;font-size:.875rem;font-weight:600;cursor:pointer;transition:background .12s,opacity .12s,color .12s;white-space:nowrap;}
            .gp-tab:hover{opacity:1;background:rgba(125,125,125,.15);}
            .gp-tab--active{background:#059669;color:#fff;opacity:1;box-shadow:0 1px 3px rgba(0,0,0,.18);}
        </style>
        @php
            $tabs = [
                'erlaeuterung' => 'Erläuterung',
                'stammdaten' => 'Stammdaten',
                'uebersicht' => 'Übersicht',
                'umsatz' => 'Umsatz',
                'lohn' => 'Lohn',
                'pacht' => 'Pacht',
                'finanzierung' => 'Finanzierung',
                'kosten' => 'Kosten',
                'liquiditaet' => 'Liquidität',
            ];
        @endphp
        <div x-data="{ tab: 'erlaeuterung' }">
            <div class="gp-tabs">
                @foreach ($tabs as $key => $label)
                    <button type="button" class="gp-tab" :class="tab==='{{ $key }}' && 'gp-tab--active'" x-on:click="tab='{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>

        <div x-show="tab==='erlaeuterung'" x-cloak>
        {{-- Erläuterung / Bedienung --}}
        <x-filament::section>
            <x-slot name="heading">Bedienung der Geschäftsplanung</x-slot>
            <x-slot name="description">Version 2024.1 – bitte alle Eingabefelder ausfüllen. Wenn ein Wert nicht vorhanden ist, 0 eintragen.</x-slot>

            <div style="font-size:.9rem;line-height:1.6;max-width:60rem;">
                <p style="font-weight:700;margin-bottom:.25rem;">Allgemein</p>
                <ul style="margin:0 0 1rem 1.1rem;list-style:disc;">
                    <li>Eingabefelder befinden sich in den Tabs <strong>Stammdaten, Umsatz, Lohn, Pacht, Finanzierung, Kosten</strong>.</li>
                    <li>Türkis hinterlegte/abgeleitete Werte (z. B. Personalkosten, Pacht, Zinsen) werden automatisch berechnet und sind nur lesbar.</li>
                    <li>Die <strong>Übersicht</strong> und die <strong>Liquidität</strong> rechnen live mit – kein Speichern nötig zum Rechnen, aber zum dauerhaften Sichern auf „Plan speichern".</li>
                </ul>

                <p style="font-weight:700;margin-bottom:.25rem;">1. Stammdaten ausfüllen</p>
                <ul style="margin:0 0 1rem 1.1rem;list-style:disc;">
                    <li><strong>Planjahr</strong> und <strong>Planung ab Monat</strong> – wichtig bei Neugründung, wenn die Station nicht ab 01.01. übernommen wird (das erste Jahr wird anteilig heruntergerechnet).</li>
                    <li><strong>Gewerbesteuer</strong> einbeziehen + Hebesatz → handels- und steuerrechtlicher Gewinn.</li>
                    <li><strong>Mehrjahresplanung</strong> über drei Jahre zeigt Potenziale und Entwicklung.</li>
                    <li><strong>Unternehmer- und Tankstellendaten</strong>: Backshop, Gastronomie, Kaffee, Nebengeschäfte, Unternehmer-PKW, digitale Buchhaltung usw.</li>
                    <li><strong>Öffnungszeiten</strong> dienen (im nächsten Schritt) der automatischen Berechnung der Personalstunden.</li>
                </ul>

                <p style="font-weight:700;margin-bottom:.25rem;">2. Umsatzplan</p>
                <ul style="margin:0 0 1rem 1.1rem;list-style:disc;">
                    <li>Geplanten Jahresumsatz und die Margen (BVD %) eintragen; kein Umsatz → 0,00.</li>
                    <li>Bei Mehrjahresplanung können die Folgejahre über Steigerungen fortgeschrieben werden.</li>
                </ul>

                <p style="font-weight:700;margin-bottom:.25rem;">3. Kosten-, Lohn- und Pachtplan</p>
                <ul style="margin:0 0 1rem 1.1rem;list-style:disc;">
                    <li>Geplante Jahreskosten eintragen; Personalkosten, Pacht und Zinsen werden aus Lohn-, Pacht- und Finanzierungs-Tab berechnet.</li>
                </ul>

                <p style="font-weight:700;margin-bottom:.4rem;">Ø Stundenlohn Einzelhandel nach Bundesland (Richtwert, Stand 2022)</p>
                <div style="overflow-x:auto;">
                    <table style="border-collapse:collapse;font-size:.82rem;">
                        @php
                            $loehne = [
                                'Baden-Württemberg' => '16,97', 'Bayern' => '16,23', 'Berlin' => '16,14',
                                'Brandenburg' => '13,06', 'Bremen' => '16,01', 'Hamburg' => '18,50',
                                'Hessen' => '17,59', 'Mecklenburg-Vorpommern' => '12,70', 'Niedersachsen' => '15,23',
                                'Nordrhein-Westfalen' => '16,96', 'Rheinland-Pfalz' => '15,53', 'Saarland' => '15,48',
                                'Sachsen' => '13,41', 'Sachsen-Anhalt' => '12,26', 'Schleswig-Holstein' => '14,53',
                                'Thüringen' => '12,72',
                            ];
                        @endphp
                        <tbody>
                            @foreach ($loehne as $land => $lohn)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.2rem .8rem .2rem 0;">{{ $land }}</td>
                                    <td style="padding:.2rem 0;text-align:right;">{{ $lohn }} €</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p style="font-size:.8rem;opacity:.6;margin-top:.6rem;">Ø Einzelhandel gesamt 2022: 16,10 €. Backshop-Zusatzstunden richten sich nach dem Umsatz (bis 15.000 € = 1 Std/Tag, je weitere 10.000 € + 1 Std, max. 8).</p>
            </div>
        </x-filament::section>
        </div>

        <div x-show="tab==='stammdaten'" x-cloak>
        {{-- Stammdaten --}}
        <x-filament::section>
            <x-slot name="heading">Stammdaten (Eingabe)</x-slot>
            @php
                $lbl = 'font-size:.78rem;font-weight:600;display:block;margin-bottom:.15rem;';
                $grp = 'font-weight:800;font-size:.95rem;margin:1.4rem 0 .6rem;padding-bottom:.25rem;border-bottom:1px solid rgba(120,120,120,.2);';
                $grid = 'display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:.7rem;';
                $num = $inpTxt . ';text-align:right;';
            @endphp

            {{-- Allgemein --}}
            <div style="{{ $grp }};margin-top:0;">Allgemein</div>
            <div style="{{ $grid }}">
                <div><label style="{{ $lbl }}">Erstes Planjahr</label><input type="number" wire:model.live="stamm.year_from" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Letztes Planjahr</label><input type="number" wire:model.live="stamm.year_to" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Planung ab Monat (1–12)</label><input type="number" min="1" max="12" wire:model.live.debounce.400ms="stamm.plan_start_month" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Gewerbesteuer Hebesatz (%)</label><input type="text" wire:model.live.debounce.400ms="stamm.gewst_hebesatz" style="{{ $num }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;">
                    <input type="checkbox" wire:model.live="stamm.gewst_enabled" id="f_gewst" style="width:1.05rem;height:1.05rem;">
                    <label for="f_gewst" style="font-size:.8rem;font-weight:600;">Gewerbesteuer einbeziehen</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;">
                    <input type="checkbox" wire:model.live="stamm.neugruendung" id="f_neu" style="width:1.05rem;height:1.05rem;">
                    <label for="f_neu" style="font-size:.8rem;font-weight:600;">Neugründung</label></div>
            </div>

            {{-- Unternehmerdaten --}}
            <div style="{{ $grp }}">Unternehmerdaten</div>
            <div style="{{ $grid }}">
                <div><label style="{{ $lbl }}">Mineralölgesellschaft</label><input type="text" wire:model.live.debounce.400ms="stamm.mineraloel" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Titel des Plans</label><input type="text" wire:model.live.debounce.400ms="stamm.title" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Name Inhaber</label><input type="text" wire:model.live.debounce.400ms="stamm.inhaber" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">TS-Name</label><input type="text" wire:model.live.debounce.400ms="stamm.ts_name" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Straße / Hausnummer</label><input type="text" wire:model.live.debounce.400ms="stamm.address" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">PLZ / Ort</label><input type="text" wire:model.live.debounce.400ms="stamm.city" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Bundesland</label><input type="text" wire:model.live.debounce.400ms="stamm.bundesland" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Telefon</label><input type="text" wire:model.live.debounce.400ms="stamm.telefon" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">E-Mail</label><input type="text" wire:model.live.debounce.400ms="stamm.email" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Unternehmensform</label>
                    <select wire:model.live="stamm.unternehmensform" style="{{ $inpTxt }};text-align:left;">
                        @foreach (['Einzelunternehmen','GbR','GmbH','GmbH & Co. KG','UG (haftungsbeschränkt)','OHG','KG'] as $uf)
                            <option value="{{ $uf }}">{{ $uf }}</option>
                        @endforeach
                    </select></div>
                <div><label style="{{ $lbl }}">Mehrfachbetreiber? / Anz. Stationen</label><input type="text" wire:model.live.debounce.400ms="stamm.mehrfachbetreiber" style="{{ $inpTxt }}"></div>
            </div>

            {{-- Tankstellendaten --}}
            <div style="{{ $grp }}">Tankstellendaten</div>
            <div style="{{ $grid }}">
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.backshop" id="f_backshop" style="width:1.05rem;height:1.05rem;"><label for="f_backshop" style="font-size:.8rem;font-weight:600;">Backshop?</label></div>
                <div><label style="{{ $lbl }}">% Verderb Backshop</label><input type="text" wire:model.live.debounce.400ms="stamm.verderb_backshop_pct" style="{{ $num }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.gastronomie" id="f_gastro" style="width:1.05rem;height:1.05rem;"><label for="f_gastro" style="font-size:.8rem;font-weight:600;">Gastronomie?</label></div>
                <div><label style="{{ $lbl }}">% Verderb Gastro</label><input type="text" wire:model.live.debounce.400ms="stamm.verderb_gastro_pct" style="{{ $num }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.pfandschlupf" id="f_pfand" style="width:1.05rem;height:1.05rem;"><label for="f_pfand" style="font-size:.8rem;font-weight:600;">Pfandschlupf berechnen?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.kaffeeautomat" id="f_kaffee" style="width:1.05rem;height:1.05rem;"><label for="f_kaffee" style="font-size:.8rem;font-weight:600;">Kaffeeautomat?</label></div>
                <div><label style="{{ $lbl }}">Kaffeekonzept</label><input type="text" wire:model.live.debounce.400ms="stamm.kaffeekonzept" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Anzahl Terminal</label><input type="text" wire:model.live.debounce.400ms="stamm.anzahl_terminal" style="{{ $num }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.mautstation" id="f_maut" style="width:1.05rem;height:1.05rem;"><label for="f_maut" style="font-size:.8rem;font-weight:600;">Mautstation?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.nebengeschaefte" id="f_neben" style="width:1.05rem;height:1.05rem;"><label for="f_neben" style="font-size:.8rem;font-weight:600;">Nebengeschäfte? (außer Lotto/Paket)</label></div>
                <div><label style="{{ $lbl }}">Art Nebengeschäft 1</label><input type="text" wire:model.live.debounce.400ms="stamm.nebengeschaeft1" style="{{ $inpTxt }}"></div>
                <div><label style="{{ $lbl }}">Art Nebengeschäft 2</label><input type="text" wire:model.live.debounce.400ms="stamm.nebengeschaeft2" style="{{ $inpTxt }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.unternehmer_pkw" id="f_pkw" style="width:1.05rem;height:1.05rem;"><label for="f_pkw" style="font-size:.8rem;font-weight:600;">Unternehmer-PKW vorhanden?</label></div>
                <div><label style="{{ $lbl }}">Bruttolistenpreis (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.bruttolistenpreis" style="{{ $num }}"></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.digitale_buchhaltung" id="f_dbh" style="width:1.05rem;height:1.05rem;"><label for="f_dbh" style="font-size:.8rem;font-weight:600;">Digitale Buchhaltung?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.mandant_contax" id="f_contax" style="width:1.05rem;height:1.05rem;"><label for="f_contax" style="font-size:.8rem;font-weight:600;">Mandant Contax?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.verfahrensdoku" id="f_vdoku" style="width:1.05rem;height:1.05rem;"><label for="f_vdoku" style="font-size:.8rem;font-weight:600;">Verfahrensdokumentation nötig?</label></div>
            </div>

            {{-- Waschgeschäft & Kfz --}}
            <div style="{{ $grp }}">Waschgeschäft & Kfz-Dienstleistungen</div>
            <div style="{{ $grid }}">
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.werkstatt" id="f_werk" style="width:1.05rem;height:1.05rem;"><label for="f_werk" style="font-size:.8rem;font-weight:600;">Werkstatt / Kfz-Aufbereitung?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.muenzgeraete" id="f_muenz" style="width:1.05rem;height:1.05rem;"><label for="f_muenz" style="font-size:.8rem;font-weight:600;">Münzgeräte?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.waschanlage" id="f_wasch" style="width:1.05rem;height:1.05rem;"><label for="f_wasch" style="font-size:.8rem;font-weight:600;">Waschanlage?</label></div>
                <div style="display:flex;align-items:center;gap:.5rem;padding-top:1.4rem;"><input type="checkbox" wire:model.live="stamm.wasseraufbereitung" id="f_wasser" style="width:1.05rem;height:1.05rem;"><label for="f_wasser" style="font-size:.8rem;font-weight:600;">Wasseraufbereitung?</label></div>
                <div><label style="{{ $lbl }}">Anzahl Wäschen</label><input type="text" wire:model.live.debounce.400ms="stamm.anzahl_waeschen" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Ø Waschpreis (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.waschpreis" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Anzahl Wäschen 1</label><input type="text" wire:model.live.debounce.400ms="stamm.anzahl_waeschen_1" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Waschpreis / Wäsche 1 (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.waschpreis_1" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Anzahl Wäschen 2</label><input type="text" wire:model.live.debounce.400ms="stamm.anzahl_waeschen_2" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Waschpreis / Wäsche 2 (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.waschpreis_2" style="{{ $num }}"></div>
            </div>

            {{-- Finanz- & Liquiditätsannahmen --}}
            <div style="{{ $grp }}">Finanz- & Liquiditätsannahmen</div>
            <div style="{{ $grid }}">
                <div><label style="{{ $lbl }}">Zinssatz Finanzierung (%)</label><input type="text" wire:model.live.debounce.400ms="stamm.interest_rate" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Tilgung / Jahr (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.annual_repayment" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Anfangsbestand Liquidität (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.opening_balance" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">USt-Satz (%)</label><input type="text" wire:model.live.debounce.400ms="stamm.vat_rate" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Privatentnahme / Jahr (€)</label><input type="text" wire:model.live.debounce.400ms="stamm.private_draw" style="{{ $num }}"></div>
                <div><label style="{{ $lbl }}">Urlaub / Krankheit (%)</label><input type="text" wire:model.live.debounce.400ms="stamm.vacation_pct" style="{{ $num }}"></div>
            </div>
        </x-filament::section>
        </div>

        <div x-show="tab==='uebersicht'" x-cloak>
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
                        @if (! empty($stamm['gewst_enabled']))
                            <tr style="font-size:.85rem;">
                                <td style="{{ $tdL }}">./. Gewerbesteuer (nicht anrechenbar)</td>
                                @foreach ($years as $y)
                                    <td style="padding:.25rem .5rem;text-align:right;">{{ $money($ov[$y]['gewst_na'] ?? 0) }} €</td>
                                @endforeach
                            </tr>
                            <tr style="font-weight:700;border-top:1px solid rgba(120,120,120,.25);">
                                <td style="{{ $tdL }}">= Gewinn nach Steuern</td>
                                @foreach ($years as $y)
                                    @php $gns = $ov[$y]['gewinn_nach_steuern'] ?? 0; @endphp
                                    <td style="padding:.3rem .5rem;text-align:right;{{ $gns < 0 ? 'color:#dc2626;' : 'color:#059669;' }}">{{ $money($gns) }} €</td>
                                @endforeach
                            </tr>
                            <tr style="opacity:.55;font-size:.78rem;">
                                <td style="{{ $tdL }}">(volle Gewerbesteuer)</td>
                                @foreach ($years as $y)
                                    <td style="padding:.15rem .5rem;text-align:right;">{{ $money($ov[$y]['gewst'] ?? 0) }} €</td>
                                @endforeach
                            </tr>
                        @endif
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
        </div>

        <div x-show="tab==='umsatz'" x-cloak>
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
                                        <td style="padding:.15rem .25rem;"><input type="text" wire:model.live.debounce.400ms="rows.{{ $id }}.values.{{ $y }}.amount" style="{{ $inp }}"></td>
                                        <td style="padding:.15rem .25rem;"><input type="text" wire:model.live.debounce.400ms="rows.{{ $id }}.values.{{ $y }}.margin" style="{{ $inp }};min-width:60px;"></td>
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
        </div>

        <div x-show="tab==='lohn'" x-cloak>
        {{-- Personalkostenberechnung (Lohn) --}}
        @php $staffRows = collect($staff); $pay = $this->payroll; $wageGrowth = (float) str_replace(['.', ','], ['', '.'], $stamm['wage_growth_pct'] ?? '0'); @endphp
        <x-filament::section>
            <x-slot name="heading">Personalkostenberechnung (Lohn)</x-slot>
            <x-slot name="description">Je Zeile: Std/Tag × Tage/Woche × 52 × Stundenlohn = Lohn p.a. (pro Jahr planbar). Eigenanteil Unternehmer wird abgezogen. Daraus ergibt sich das Personalkostenbudget (Urlaub/Krankheit, AG-Anteil Fest/Aushilfe, Zuschläge), das in „Personalkosten" einfließt.</x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.6rem;margin-bottom:1rem;">
                <div><label style="font-size:.78rem;font-weight:600;">Anteil Festangestellte (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.staff_fest_pct" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">AG-Anteil Fest (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.ag_pct_fest" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">AG-Anteil Aushilfen (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.ag_pct_aushilfe" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Sonntagsstunden / Jahr</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.sonntag_hours" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Sonntagszuschlag (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.sonntag_pct" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Feiertagsstunden / Jahr</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.feiertag_hours" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Feiertagszuschlag (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.feiertag_pct" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Nachtstunden / Jahr</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.nacht_hours" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Nachtzuschlag (%)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.nacht_pct" style="{{ $inpTxt }};text-align:right;"></div>
                <div><label style="font-size:.78rem;font-weight:600;">Lohnsteigerung % p.a. (Mindestlohn)</label>
                    <input type="text" wire:model.live.debounce.400ms="stamm.wage_growth_pct" style="{{ $inpTxt }};text-align:right;"></div>
            </div>
            @if ($wageGrowth > 0)
                <div style="font-size:.78rem;opacity:.65;margin-bottom:.5rem;">Stundenlohn nur im ersten Planjahr eingeben – die Folgejahre werden automatisch mit {{ $money($wageGrowth) }} % p.a. hochgerechnet.</div>
            @endif
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;min-width:200px;">Bezeichnung</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}" colspan="4">{{ $y }} — Std/Tag · Tage/Wo · €/Std · Lohn p.a.</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php $areaNames = ['shop' => 'Shop', 'werkstatt' => 'Werkstatt / Kfz-Aufbereitung', 'gastro' => 'Gastronomie']; @endphp
                        @foreach ($staffRows->groupBy('area') as $area => $arows)
                            <tr><td colspan="{{ 1 + count($years) * 4 }}" style="padding:.7rem .5rem .25rem;font-weight:800;font-size:.9rem;border-bottom:1px solid rgba(120,120,120,.25);">{{ $areaNames[$area] ?? $area }}</td></tr>
                            @foreach ($arows->groupBy('category') as $group => $grows)
                                <tr><td colspan="{{ 1 + count($years) * 4 }}" style="padding:.35rem .5rem .15rem 1rem;font-weight:600;font-size:.78rem;opacity:.75;">{{ $group }}</td></tr>
                                @foreach ($grows as $s)
                                    @php $sid = $s['id']; @endphp
                                    <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                        <td style="{{ $tdL }};padding-left:1rem;">{{ $s['label'] }}@if ($s['is_deduction'])<span style="opacity:.5;font-size:.7rem;"> (abzgl.)</span>@endif</td>
                                        @foreach ($years as $y)
                                            <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="staff.{{ $sid }}.values.{{ $y }}.hpd" style="{{ $inp }};min-width:60px;"></td>
                                            <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="staff.{{ $sid }}.values.{{ $y }}.dpw" style="{{ $inp }};min-width:55px;"></td>
                                            @if ($loop->index > 0 && $wageGrowth > 0)
                                                <td style="padding:.15rem .35rem;text-align:right;font-size:.8rem;opacity:.6;min-width:60px;">{{ $money($this->effectiveWage($s, $loop->index)) }}</td>
                                            @else
                                                <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="staff.{{ $sid }}.values.{{ $y }}.wage" style="{{ $inp }};min-width:60px;"></td>
                                            @endif
                                            <td style="padding:.15rem .35rem;text-align:right;font-size:.8rem;opacity:.7;white-space:nowrap;{{ $s['is_deduction'] ? 'color:#dc2626;' : '' }}">{{ $s['is_deduction'] ? '-' : '' }}{{ $money($this->staffWage($s, $y)) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                        @php
                            $payLines = [
                                ['Lohnkosten', 'lohnkosten'],
                                ['+ Urlaub / Krankheit', 'urlaub'],
                                ['+ AG-Anteil (Fest/Aushilfe)', 'ag_anteil'],
                                ['+ Zuschläge (So./Feiertag/Nacht)', 'zuschlaege'],
                            ];
                        @endphp
                        @foreach ($payLines as [$lbl, $key])
                            <tr style="font-size:.82rem;border-top:1px solid rgba(120,120,120,.2);">
                                <td style="{{ $tdL }};text-align:right;">{{ $lbl }}</td>
                                @foreach ($years as $y)
                                    <td colspan="4" style="padding:.2rem .35rem;text-align:right;">{{ $money($pay[$y][$key] ?? 0) }} €</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-weight:700;border-top:2px solid rgba(120,120,120,.3);">
                            <td style="{{ $tdL }};text-align:right;">= Personalkostenbudget</td>
                            @foreach ($years as $y)
                                <td colspan="4" style="padding:.3rem .35rem;text-align:right;">{{ $money($pay[$y]['budget'] ?? 0) }} €</td>
                            @endforeach
                        </tr>
                        <tr style="opacity:.6;font-size:.78rem;">
                            <td style="{{ $tdL }};text-align:right;">Jahresstunden</td>
                            @foreach ($years as $y)
                                <td colspan="4" style="padding:.2rem .35rem;text-align:right;">{{ number_format($pay[$y]['hours'] ?? 0, 0, ',', '.') }} Std.</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        </div>

        <div x-show="tab==='pacht'" x-cloak>
        {{-- Pachtberechnung --}}
        @php $lease = $this->lease; $leaseRows = collect($leaseBases); @endphp
        <x-filament::section>
            <x-slot name="heading">Pachtberechnung</x-slot>
            <x-slot name="description">Stationspacht = Shopumsatzpacht (Bemessungs-Umsatz × Satz, anteilig ab Startmonat) + Festpacht (€/Monat ab Startstufe). Fließt in die Kostenposition „Pacht - Station".</x-slot>

            <div style="overflow-x:auto;margin-bottom:1rem;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(120,120,120,.25);">
                            <th style="{{ $th }};text-align:left;">Stufe</th>
                            <th style="{{ $th }}">ab Jahr</th>
                            <th style="{{ $th }}">ab Monat</th>
                            <th style="{{ $th }}">Satz-Faktor %</th>
                            <th style="{{ $th }}">Festpacht € / Monat</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($leaseStages) as $s)
                            @php $sgid = $s['id']; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.1);">
                                <td style="{{ $tdL }}">{{ $s['stage_no'] }}. Stufe</td>
                                <td style="padding:.15rem .2rem;"><input type="number" placeholder="—" wire:model.live.debounce.400ms="leaseStages.{{ $sgid }}.start_year" style="{{ $inp }};min-width:70px;"></td>
                                <td style="padding:.15rem .2rem;"><input type="number" min="1" max="12" wire:model.live.debounce.400ms="leaseStages.{{ $sgid }}.start_month" style="{{ $inp }};min-width:55px;"></td>
                                <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="leaseStages.{{ $sgid }}.rate_factor" style="{{ $inp }};min-width:70px;"></td>
                                <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="leaseStages.{{ $sgid }}.festpacht" style="{{ $inp }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="font-size:.75rem;opacity:.6;margin-top:.4rem;">Leeres „ab Jahr" = Stufe inaktiv. Aktiv ist je Monat die Stufe mit dem spätesten Start. Satz-Faktor 100 % = volle Umsatzpachtsätze; z. B. 0 % = keine Umsatzpacht, 120 % = höhere Stufe.</div>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;min-width:200px;">Bemessungsgrundlage</th>
                            <th style="{{ $th }}">Satz %</th>
                            <th style="{{ $th }}">man. Umsatz</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}">{{ $y }} Umsatz</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaseRows as $b)
                            @php $bid = $b['id']; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="{{ $tdL }}">{{ $b['label'] }}</td>
                                <td style="padding:.15rem .2rem;"><input type="text" wire:model.live.debounce.400ms="leaseBases.{{ $bid }}.rate" style="{{ $inp }};min-width:60px;"></td>
                                <td style="padding:.15rem .2rem;">
                                    @if ($b['source'] === 'manual')
                                        <input type="text" wire:model.live.debounce.400ms="leaseBases.{{ $bid }}.manual" style="{{ $inp }}">
                                    @else
                                        <span style="opacity:.4;font-size:.75rem;">automatisch</span>
                                    @endif
                                </td>
                                @foreach ($years as $y)
                                    <td style="padding:.15rem .35rem;text-align:right;font-size:.8rem;opacity:.75;white-space:nowrap;">{{ $money($this->leaseAmount($b, $y)) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                        <tr style="font-size:.85rem;border-top:1px solid rgba(120,120,120,.2);">
                            <td style="{{ $tdL }};text-align:right;" colspan="3">Shopumsatzpacht</td>
                            @foreach ($years as $y)
                                <td style="padding:.2rem .35rem;text-align:right;">{{ $money($lease[$y]['umsatzpacht'] ?? 0) }} €</td>
                            @endforeach
                        </tr>
                        <tr style="font-size:.85rem;">
                            <td style="{{ $tdL }};text-align:right;" colspan="3">+ Festpacht</td>
                            @foreach ($years as $y)
                                <td style="padding:.2rem .35rem;text-align:right;">{{ $money($lease[$y]['festpacht'] ?? 0) }} €</td>
                            @endforeach
                        </tr>
                        <tr style="font-weight:700;border-top:2px solid rgba(120,120,120,.3);">
                            <td style="{{ $tdL }};text-align:right;" colspan="3">= Pacht - Station</td>
                            @foreach ($years as $y)
                                <td style="padding:.3rem .35rem;text-align:right;">{{ $money($lease[$y]['total'] ?? 0) }} €</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        </div>

        <div x-show="tab==='kosten'" x-cloak>
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
                        @php $pay = $this->payroll; $lease = $this->lease; $interest = $this->interest; @endphp
                        @foreach ($costRows as $row)
                            @php $id = $row['id']; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="{{ $tdL }}">{{ $row['label'] }}@if ($row['label'] === 'Personalkosten')<span style="opacity:.5;font-size:.7rem;"> (aus Lohnberechnung)</span>@elseif ($row['label'] === 'Pacht - Station')<span style="opacity:.5;font-size:.7rem;"> (aus Pachtberechnung)</span>@elseif ($row['label'] === 'Zinsen- und Geldkosten')<span style="opacity:.5;font-size:.7rem;"> (aus Finanzierung)</span>@endif</td>
                                @foreach ($years as $y)
                                    @if ($row['label'] === 'Personalkosten')
                                        <td style="padding:.15rem .35rem;text-align:right;opacity:.85;">{{ $money($pay[$y]['budget'] ?? 0) }} €</td>
                                    @elseif ($row['label'] === 'Pacht - Station')
                                        <td style="padding:.15rem .35rem;text-align:right;opacity:.85;">{{ $money($lease[$y]['total'] ?? 0) }} €</td>
                                    @elseif ($row['label'] === 'Zinsen- und Geldkosten')
                                        <td style="padding:.15rem .35rem;text-align:right;opacity:.85;">{{ $money($interest[$y] ?? 0) }} €</td>
                                    @else
                                        <td style="padding:.15rem .25rem;"><input type="text" wire:model.live.debounce.400ms="rows.{{ $id }}.values.{{ $y }}.amount" style="{{ $inp }}"></td>
                                    @endif
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
        </div>

        <div x-show="tab==='finanzierung'" x-cloak>
        {{-- Finanzierung / Kapitalbedarf --}}
        @php $financeRows = collect($financings); $interest = $this->interest; $capital = $this->capitalNeed; @endphp
        <x-filament::section>
            <x-slot name="heading">Finanzierung / Kapitalbedarf</x-slot>
            <x-slot name="description">Summe des Kapitalbedarfs = Darlehen (Aufnahme im ersten Monat). Der Zinssatz (Stammdaten) erzeugt die jährlichen Zinsen → fließen in „Zinsen- und Geldkosten".</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(120,120,120,.3);">
                            <th style="{{ $th }};text-align:left;min-width:200px;">Kapitalbedarf für</th>
                            <th style="{{ $th }};text-align:left;min-width:180px;">Art der Finanzierung</th>
                            <th style="{{ $th }}">Betrag (€)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($financeRows as $f)
                            @php $fid = $f['id']; @endphp
                            <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                <td style="{{ $tdL }}">{{ $f['label'] }}</td>
                                <td style="padding:.15rem .25rem;"><input type="text" wire:model.live.debounce.400ms="financings.{{ $fid }}.finance_type" style="{{ $inpTxt }};text-align:left;"></td>
                                <td style="padding:.15rem .25rem;"><input type="text" wire:model.live.debounce.400ms="financings.{{ $fid }}.amount" style="{{ $inp }}"></td>
                            </tr>
                        @endforeach
                        <tr style="font-weight:700;border-top:2px solid rgba(120,120,120,.3);">
                            <td style="{{ $tdL }};text-align:right;" colspan="2">Kapitalbedarf = Darlehen</td>
                            <td style="padding:.3rem .25rem;text-align:right;">{{ $money($capital) }} €</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="overflow-x:auto;margin-top:.75rem;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(120,120,120,.2);">
                            <th style="{{ $th }};text-align:left;">Zinsen p.a. (Restschuld × Zinssatz)</th>
                            @foreach ($years as $y)
                                <th style="{{ $th }}">{{ $y }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="{{ $tdL }}">Zinsen</td>
                            @foreach ($years as $y)
                                <td style="padding:.2rem .35rem;text-align:right;">{{ $money($interest[$y] ?? 0) }} €</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        </div>

        <div x-show="tab==='liquiditaet'" x-cloak>
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
                ['./. Gewerbesteuer', 'gewst', '-'],
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
        </div>
        </div>
    @else
        <x-filament::section>
            <div style="padding:1rem;text-align:center;opacity:.6;">Bitte einen Plan wählen oder mit „Neuer Plan" anlegen.</div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
