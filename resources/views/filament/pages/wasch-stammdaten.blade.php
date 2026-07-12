<x-filament-panels::page>
    @php
        $inp = 'padding:.3rem .45rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;font-size:.82rem;';
        $kat = ['eigen' => 'Eigenfahrzeug', 'mitarbeiter' => 'Mitarbeiter', 'test' => 'Testwäsche'];
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- ARTIKEL je Station --}}
        <x-filament::section>
            <x-slot name="heading">Kassen-Artikel je Station</x-slot>
            <x-slot name="description">Gleiche Programme/Preise an beiden Stationen, aber je Station eigene EAN. Preis leer lassen heißt „kein VK hinterlegt".</x-slot>

            @foreach ($this->businesses as $business)
                <div style="margin-bottom:1rem;">
                    <div style="font-weight:600;margin-bottom:.35rem;">{{ $business->display_label }}</div>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                            <thead>
                                <tr style="text-align:left;border-bottom:1px solid rgba(120,120,120,.2);opacity:.7;">
                                    <th style="padding:.3rem;">Programm</th><th style="padding:.3rem;">Bezeichnung (Kasse)</th>
                                    <th style="padding:.3rem;">Art</th><th style="padding:.3rem;">EAN</th>
                                    <th style="padding:.3rem;">VK</th><th style="padding:.3rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->articlesByBusiness->get($business->id, collect()) as $a)
                                    <tr style="border-bottom:1px solid rgba(120,120,120,.1);">
                                        <td style="padding:.3rem;font-weight:600;">{{ $a->program }}</td>
                                        <td style="padding:.3rem;"><input type="text" value="{{ $a->name }}" style="{{ $inp }}width:12rem;"
                                            wire:change="updateArticle({{ $a->id }}, 'name', $event.target.value)"></td>
                                        <td style="padding:.3rem;">
                                            <select style="{{ $inp }}" wire:change="updateArticle({{ $a->id }}, 'type', $event.target.value)">
                                                <option value="einzel" @selected($a->type === 'einzel')>Einzel</option>
                                                <option value="flatrate" @selected($a->type === 'flatrate')>Flatrate</option>
                                            </select>
                                        </td>
                                        <td style="padding:.3rem;"><input type="text" value="{{ $a->ean }}" placeholder="EAN…" style="{{ $inp }}width:11rem;font-family:monospace;"
                                            wire:change="updateArticle({{ $a->id }}, 'ean', $event.target.value)"></td>
                                        <td style="padding:.3rem;"><input type="text" value="{{ $a->price !== null ? number_format((float)$a->price, 2, ',', '') : '' }}" placeholder="0,00" style="{{ $inp }}width:5rem;text-align:right;"
                                            wire:change="updateArticle({{ $a->id }}, 'price', $event.target.value)"> €</td>
                                        <td style="padding:.3rem;text-align:right;">
                                            <button type="button" wire:click="deleteArticle({{ $a->id }})" wire:confirm="Artikel löschen?"
                                                style="color:#dc2626;background:none;border:0;cursor:pointer;font-size:.8rem;">löschen</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" style="padding:.5rem;opacity:.5;">Noch keine Artikel.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach

            {{-- Artikel hinzufügen --}}
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-top:.5rem;padding-top:.6rem;border-top:1px solid rgba(120,120,120,.15);">
                <span style="font-weight:600;font-size:.85rem;">Neu:</span>
                <select wire:model="naBusiness" style="{{ $inp }}">
                    <option value="">Station…</option>
                    @foreach ($this->businesses as $b)
                        <option value="{{ $b->id }}">{{ $b->short_name ?: $b->display_label }}</option>
                    @endforeach
                </select>
                <input type="text" wire:model="naProgram" placeholder="Programm (z. B. Basis)" style="{{ $inp }}width:9rem;">
                <input type="text" wire:model="naName" placeholder="Kassen-Bezeichnung" style="{{ $inp }}width:11rem;">
                <select wire:model="naType" style="{{ $inp }}"><option value="einzel">Einzel</option><option value="flatrate">Flatrate</option></select>
                <input type="text" wire:model="naEan" placeholder="EAN" style="{{ $inp }}width:10rem;font-family:monospace;">
                <input type="text" wire:model="naVk" placeholder="VK" style="{{ $inp }}width:5rem;text-align:right;">
                <x-filament::button wire:click="addArticle" size="sm" icon="heroicon-o-plus">Anlegen</x-filament::button>
            </div>
        </x-filament::section>

        {{-- KENNZEICHEN --}}
        <x-filament::section>
            <x-slot name="heading">Freiwäsche-Kennzeichen</x-slot>
            <x-slot name="description">Wäschen mit diesen Kennzeichen werden beim Import als Freiwäsche markiert. Mitarbeiter werden gesondert gekennzeichnet (Sachbezug).</x-slot>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(120,120,120,.2);opacity:.7;">
                            <th style="padding:.3rem;">Kennzeichen</th><th style="padding:.3rem;">Kategorie</th>
                            <th style="padding:.3rem;">Notiz</th><th style="padding:.3rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->plates as $pl)
                            <tr style="border-bottom:1px solid rgba(120,120,120,.1);">
                                <td style="padding:.3rem;"><input type="text" value="{{ $pl->plate }}" style="{{ $inp }}width:9rem;"
                                    wire:change="updatePlate({{ $pl->id }}, 'plate', $event.target.value)"></td>
                                <td style="padding:.3rem;">
                                    <select style="{{ $inp }}" wire:change="updatePlate({{ $pl->id }}, 'category', $event.target.value)">
                                        @foreach ($kat as $k => $lbl)
                                            <option value="{{ $k }}" @selected($pl->category === $k)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding:.3rem;"><input type="text" value="{{ $pl->note }}" placeholder="optional" style="{{ $inp }}width:14rem;"
                                    wire:change="updatePlate({{ $pl->id }}, 'note', $event.target.value)"></td>
                                <td style="padding:.3rem;text-align:right;">
                                    <button type="button" wire:click="deletePlate({{ $pl->id }})" wire:confirm="Kennzeichen löschen?"
                                        style="color:#dc2626;background:none;border:0;cursor:pointer;font-size:.8rem;">löschen</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-top:.5rem;padding-top:.6rem;border-top:1px solid rgba(120,120,120,.15);">
                <span style="font-weight:600;font-size:.85rem;">Neu:</span>
                <input type="text" wire:model="newPlate" placeholder="Kennzeichen" style="{{ $inp }}width:9rem;">
                <select wire:model="newPlateCategory" style="{{ $inp }}">
                    @foreach ($kat as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach
                </select>
                <x-filament::button wire:click="addPlate" size="sm" icon="heroicon-o-plus">Hinzufügen</x-filament::button>
            </div>
        </x-filament::section>

        {{-- STATE-CODES --}}
        <x-filament::section>
            <x-slot name="heading">State-Codes (Status im Export)</x-slot>
            <x-slot name="description">Lege fest, was die Codes bedeuten und ob sie als Umsatz zählen (Storno/Erstattung = Haken raus).</x-slot>

            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:.82rem;max-width:40rem;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid rgba(120,120,120,.2);opacity:.7;">
                            <th style="padding:.3rem;">Code</th><th style="padding:.3rem;">Bedeutung</th><th style="padding:.3rem;">zählt als Umsatz</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->states as $s)
                            <tr style="border-bottom:1px solid rgba(120,120,120,.1);">
                                <td style="padding:.3rem;font-weight:600;">{{ $s->code }}</td>
                                <td style="padding:.3rem;"><input type="text" value="{{ $s->label }}" style="{{ $inp }}width:16rem;"
                                    wire:change="updateState({{ $s->id }}, 'label', $event.target.value)"></td>
                                <td style="padding:.3rem;">
                                    <input type="checkbox" @checked($s->counts_as_revenue) style="width:1.1rem;height:1.1rem;"
                                        wire:change="updateState({{ $s->id }}, 'counts_as_revenue', $event.target.checked)">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-top:.5rem;padding-top:.6rem;border-top:1px solid rgba(120,120,120,.15);">
                <span style="font-weight:600;font-size:.85rem;">Neu:</span>
                <input type="number" wire:model="newStateCode" placeholder="Code" style="{{ $inp }}width:5rem;">
                <input type="text" wire:model="newStateLabel" placeholder="Bedeutung" style="{{ $inp }}width:14rem;">
                <x-filament::button wire:click="addState" size="sm" icon="heroicon-o-plus">Hinzufügen</x-filament::button>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
