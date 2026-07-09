<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-6">

        {{-- Header-Banner --}}
        <div style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem;border:1px solid rgba(16,185,129,.25);border-radius:.85rem;background:rgba(16,185,129,.06);">
            <div style="display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex-shrink:0;border-radius:.6rem;background:#059669;color:#fff;">
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" style="width:1.65rem;height:1.65rem;" />
            </div>
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <h2 style="font-size:1rem;font-weight:600;margin:0;">Hinweise an das Steuerbüro</h2>
                <p style="font-size:.875rem;margin:0;color:rgb(107,114,128);line-height:1.45;">
                    Karten je Bankkonto und Monat – per Drag & Drop sortierbar. Sie erscheinen
                    im Monatsbericht auf Seite 2 unter der Zusammenfassung, in genau dieser Reihenfolge.
                </p>
            </div>
        </div>

        <x-filament::section>
            {{ $this->form }}

            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(120,120,120,.15);">
                {{ $this->saveAction }}
            </div>
        </x-filament::section>

        {{-- PDF-Dateien für den Steuerberater --}}
        <x-filament::section>
            <x-slot name="heading">PDF-Dateien (z. B. Monatsrechnung)</x-slot>
            <x-slot name="description">Dateien zum oben gewählten Konto &amp; Monat hochladen und einer Kategorie zuordnen. Sie werden im Bericht <strong>vor</strong> den Kontoauszug-Belegen einsortiert und je Monat ab 1 nummeriert.</x-slot>

            @php $inpTxt = 'width:100%;padding:.4rem .6rem;border:1px solid rgba(120,120,120,.3);border-radius:.5rem;background:transparent;'; @endphp
            {{-- Große Drop-Fläche: Dateien hierher ziehen oder klicken --}}
            <div x-data="{ dragging:false }"
                x-on:dragover.prevent="dragging=true"
                x-on:dragleave.prevent="dragging=false"
                x-on:drop.prevent="dragging=false; $refs.docInput.files=$event.dataTransfer.files; $refs.docInput.dispatchEvent(new Event('change',{bubbles:true}))"
                x-on:click="$refs.docInput.click()"
                :style="dragging ? 'background:rgba(16,185,129,.10);border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15);' : ''"
                style="border:2px dashed rgba(16,185,129,.55);border-radius:.9rem;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:.15s;background:rgba(16,185,129,.03);">
                <div style="font-size:1.9rem;line-height:1;">⬆️</div>
                <div style="margin-top:.4rem;font-weight:600;color:#059669;">PDF-Dateien hierher ziehen</div>
                <div style="font-size:.82rem;opacity:.6;margin-top:.15rem;">oder klicken zum Auswählen · mehrere möglich</div>
                @if (! empty($docUploads))
                    <div style="margin-top:.5rem;font-size:.82rem;font-weight:600;color:#059669;">{{ count($docUploads) }} Datei(en) bereit</div>
                @endif
                <input type="file" x-ref="docInput" wire:model="docUploads" multiple accept="application/pdf,image/*" style="display:none">
            </div>
            <div wire:loading wire:target="docUploads" style="font-size:.8rem;opacity:.7;margin-top:.4rem;text-align:center;">Datei wird hochgeladen …</div>

            {{-- Standardwerte für neue Dateien + Hochladen. Feineinstellung (Konto,
                 Monat, Druck) je Datei unten in der Tabelle. --}}
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;margin-top:.9rem;">
                <div style="min-width:180px;flex:1;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Kategorie (Standard)</label>
                    <input list="doc-cats" type="text" wire:model="docCategory" placeholder="z. B. Monatsrechnung" style="{{ $inpTxt }}">
                    <datalist id="doc-cats">
                        @foreach ($this->categorySuggestions as $c)<option value="{{ $c }}"></option>@endforeach
                    </datalist>
                </div>
                <div style="min-width:220px;flex:1;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Notiz (Standard, optional)</label>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <select wire:model="docNote" style="{{ $inpTxt }};flex:1;">
                            <option value="">– kein Hinweis –</option>
                            @foreach ($this->noteTexts as $nt)
                                <option value="{{ $nt->text }}">{{ $nt->text }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="$toggle('showNewNote')" title="Neuen Hinweis-Text anlegen"
                            style="width:2.5rem;height:2.5rem;flex-shrink:0;border:1px solid #059669;color:#059669;background:transparent;border-radius:.5rem;font-size:1.25rem;font-weight:700;cursor:pointer;line-height:1;">+</button>
                    </div>
                </div>
                <label style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;font-weight:600;cursor:pointer;padding-bottom:.5rem;">
                    <input type="checkbox" wire:model="docPrint" style="width:1.05rem;height:1.05rem;"> Drucken
                </label>
                <x-filament::button wire:click="uploadDocuments" wire:loading.attr="disabled" wire:target="docUploads,uploadDocuments" icon="heroicon-o-arrow-up-tray">Hochladen &amp; zuordnen</x-filament::button>
            </div>
            @if ($showNewNote)
                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                    <input type="text" wire:model="newNoteText" wire:keydown.enter="addNoteText" placeholder="Neuen Hinweis-Text eingeben …" style="{{ $inpTxt }};flex:1;">
                    <x-filament::button wire:click="addNoteText" icon="heroicon-o-plus">Hinzufügen</x-filament::button>
                </div>
            @endif
            <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">Tipp: <strong>Konto, Monat und „Druck" kannst du unten je Datei einzeln ändern</strong> – einfach viele Dateien hochladen und dann pro Zeile zuordnen.</p>

            @php $docs = $this->documents; $noteTexts = $this->noteTexts; @endphp
            <div style="margin-top:1.25rem;">
                @if ($docs->isNotEmpty())
                    @php $sel = 'padding:.2rem .4rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;font-size:.78rem;background:transparent;'; @endphp
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="border-bottom:2px solid rgba(120,120,120,.25);text-align:left;">
                                <th style="padding:.4rem .5rem;width:40px;">Nr.</th>
                                <th style="padding:.4rem .5rem;">Datei &amp; Zuordnung</th>
                                <th style="padding:.4rem .5rem;width:60px;text-align:center;">Druck</th>
                                <th style="padding:.4rem .5rem;width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($docs as $i => $doc)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.5rem .5rem;font-weight:700;vertical-align:top;">{{ $i + 1 }}</td>
                                    <td style="padding:.5rem .5rem;">
                                        <a href="{{ $doc->preview_url }}" target="_blank" style="color:#059669;text-decoration:underline;font-weight:500;">{{ $doc->file_name ?: 'Datei' }}</a>
                                        {{-- Je Datei zuordnen: Konto · Monat · Jahr · Kategorie --}}
                                        <div style="display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.35rem;">
                                            <select wire:change="setDocAccount({{ $doc->id }}, $event.target.value)" title="Konto" style="{{ $sel }}">
                                                @foreach ($this->accountOptions as $aid => $albl)
                                                    <option value="{{ $aid }}" @selected($doc->bank_account_id == $aid)>{{ $albl }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:change="setDocMonth({{ $doc->id }}, $event.target.value)" title="Monat" style="{{ $sel }}">
                                                @foreach ($this->monthNames() as $mn => $mlabel)
                                                    <option value="{{ $mn }}" @selected((int) $doc->period->month === $mn)>{{ $mlabel }}</option>
                                                @endforeach
                                            </select>
                                            <select wire:change="setDocYear({{ $doc->id }}, $event.target.value)" title="Jahr" style="{{ $sel }}">
                                                @foreach ($this->yearOptions() as $yv => $ylabel)
                                                    <option value="{{ $yv }}" @selected((int) $doc->period->year === $yv)>{{ $ylabel }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" value="{{ $doc->category }}" title="Kategorie"
                                                wire:change="setDocCategory({{ $doc->id }}, $event.target.value)"
                                                list="doc-cats" placeholder="Kategorie" style="{{ $sel }};min-width:130px;">
                                        </div>
                                        <select wire:change="saveDocNote({{ $doc->id }}, $event.target.value)" style="width:100%;margin-top:.3rem;padding:.25rem .45rem;border:1px solid rgba(245,158,11,.5);border-radius:.4rem;background:rgba(254,243,199,.35);font-size:.8rem;">
                                            <option value="">– kein Hinweis –</option>
                                            @foreach ($noteTexts as $nt)
                                                <option value="{{ $nt->text }}" @selected($doc->note === $nt->text)>{{ $nt->text }}</option>
                                            @endforeach
                                            @if ($doc->note && ! $noteTexts->contains('text', $doc->note))
                                                <option value="{{ $doc->note }}" selected>{{ $doc->note }}</option>
                                            @endif
                                        </select>
                                    </td>
                                    <td style="padding:.5rem .5rem;text-align:center;vertical-align:top;">
                                        <input type="checkbox" @checked($doc->include_in_report) wire:change="setDocPrint({{ $doc->id }}, $event.target.checked)" title="Im Bericht drucken" style="width:1.05rem;height:1.05rem;">
                                    </td>
                                    <td style="padding:.5rem .5rem;vertical-align:top;">
                                        <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Dieses Dokument wirklich löschen?" style="color:#dc2626;font-weight:600;background:none;border:0;cursor:pointer;">Löschen</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">Diese Dateien (Konto &amp; Monat wie oben gewählt) erhalten im Bericht die Nummern 1–{{ $docs->count() }}; die Kontoauszug-Belege beginnen danach. Änderst du Konto/Monat einer Datei, wandert sie in die passende Ansicht.</p>
                @else
                    <div style="font-size:.85rem;opacity:.6;">Noch keine Dateien für diesen Monat hochgeladen.</div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
