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
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end;">
                <div style="min-width:220px;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Kategorie</label>
                    <input list="doc-cats" type="text" wire:model="docCategory" placeholder="z. B. Monatsrechnung" style="{{ $inpTxt }}">
                    <datalist id="doc-cats">
                        @foreach ($this->categorySuggestions as $c)<option value="{{ $c }}"></option>@endforeach
                    </datalist>
                </div>
                <div style="flex:1;min-width:260px;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">PDF-Dateien</label>
                    <input type="file" wire:model="docUploads" multiple accept="application/pdf,image/*" style="{{ $inpTxt }}">
                </div>
                <div>
                    <x-filament::button wire:click="uploadDocuments" wire:loading.attr="disabled" wire:target="docUploads,uploadDocuments" icon="heroicon-o-arrow-up-tray">Hochladen &amp; zuordnen</x-filament::button>
                </div>
            </div>
            <div style="margin-top:.75rem;">
                <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Notiz / Hinweis für den Steuerberater (optional)</label>
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
                @if ($showNewNote)
                    <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                        <input type="text" wire:model="newNoteText" wire:keydown.enter="addNoteText" placeholder="Neuen Hinweis-Text eingeben …" style="{{ $inpTxt }};flex:1;">
                        <x-filament::button wire:click="addNoteText" icon="heroicon-o-plus">Hinzufügen</x-filament::button>
                    </div>
                @endif
            </div>
            <label style="display:flex;align-items:center;gap:.5rem;margin-top:.6rem;font-size:.82rem;font-weight:600;cursor:pointer;">
                <input type="checkbox" wire:model="docPrint" style="width:1.05rem;height:1.05rem;"> Im Bericht drucken (sonst nur speichern)
            </label>
            <div wire:loading wire:target="docUploads" style="font-size:.8rem;opacity:.7;margin-top:.5rem;">Datei wird hochgeladen …</div>

            @php $docs = $this->documents; $noteTexts = $this->noteTexts; @endphp
            <div style="margin-top:1.25rem;">
                @if ($docs->isNotEmpty())
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="border-bottom:2px solid rgba(120,120,120,.25);text-align:left;">
                                <th style="padding:.4rem .5rem;width:48px;">Nr.</th>
                                <th style="padding:.4rem .5rem;">Kategorie</th>
                                <th style="padding:.4rem .5rem;">Datei</th>
                                <th style="padding:.4rem .5rem;width:70px;text-align:center;">Druck</th>
                                <th style="padding:.4rem .5rem;width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($docs as $i => $doc)
                                <tr style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.35rem .5rem;font-weight:700;">{{ $i + 1 }}</td>
                                    <td style="padding:.35rem .5rem;">{{ $doc->category }}</td>
                                    <td style="padding:.35rem .5rem;">
                                        <a href="{{ $doc->preview_url }}" target="_blank" style="color:#059669;text-decoration:underline;">{{ $doc->file_name ?: 'Datei' }}</a>
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
                                    <td style="padding:.35rem .5rem;text-align:center;">
                                        <input type="checkbox" @checked($doc->include_in_report) wire:change="setDocPrint({{ $doc->id }}, $event.target.checked)" title="Im Bericht drucken" style="width:1.05rem;height:1.05rem;">
                                    </td>
                                    <td style="padding:.35rem .5rem;">
                                        <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Dieses Dokument wirklich löschen?" style="color:#dc2626;font-weight:600;background:none;border:0;cursor:pointer;">Löschen</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">Diese Dateien erhalten im Bericht die Nummern 1–{{ $docs->count() }}; die Kontoauszug-Belege beginnen danach.</p>
                @else
                    <div style="font-size:.85rem;opacity:.6;">Noch keine Dateien für diesen Monat hochgeladen.</div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
