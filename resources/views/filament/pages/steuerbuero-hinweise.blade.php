<x-filament-panels::page>
    <style>[x-cloak]{display:none!important}</style>
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
            <x-slot name="heading">PDF-Dateien (z. B. Monatsrechnung, Kontoauszug)</x-slot>
            <x-slot name="description"><strong>Einfach viele Dateien auf einmal hochladen</strong> und unten je Zeile Konto, Monat, Kategorie und „Druck" zuordnen. Konto/Monat oben sind nur die Vorgaben für neue Dateien. Im Bericht werden sie <strong>vor</strong> den Kontoauszug-Belegen einsortiert und je Monat ab 1 nummeriert.</x-slot>

            @php $inpTxt = 'width:100%;padding:.4rem .6rem;border:1px solid rgba(120,120,120,.3);border-radius:.5rem;background:transparent;'; @endphp
            {{-- Häppchen-Upload: umgeht die Server-Grenze max_file_uploads (all-inkl
                 = 20). Viele Dateien wählen, sie werden automatisch in Portionen
                 à 15 hochgeladen. --}}
            <div x-data="docUploader()">
                {{-- Große Drop-Fläche: Dateien hierher ziehen oder klicken --}}
                <div
                    x-on:dragover.prevent="dragging=true"
                    x-on:dragleave.prevent="dragging=false"
                    x-on:drop.prevent="dragging=false; addFiles($event.dataTransfer.files)"
                    x-on:click="$refs.docInput.click()"
                    :style="dragging ? 'background:rgba(16,185,129,.10);border-color:#10b981;box-shadow:0 0 0 3px rgba(16,185,129,.15);' : ''"
                    style="border:2px dashed rgba(16,185,129,.55);border-radius:.9rem;padding:1.5rem 1rem;text-align:center;cursor:pointer;transition:.15s;background:rgba(16,185,129,.03);">
                    <div style="font-size:1.9rem;line-height:1;">⬆️</div>
                    <div style="margin-top:.4rem;font-weight:600;color:#059669;">PDF-Dateien hierher ziehen</div>
                    <div style="font-size:.82rem;opacity:.6;margin-top:.15rem;">oder klicken zum Auswählen · viele möglich (bis 500)</div>
                    <div x-show="files.length && !uploading" x-cloak style="margin-top:.5rem;font-size:.82rem;font-weight:600;color:#059669;"><span x-text="files.length"></span> Datei(en) bereit</div>
                    <div x-show="uploading" x-cloak style="margin-top:.5rem;font-size:.82rem;font-weight:600;color:#059669;">Lädt hoch… <span x-text="done"></span> / <span x-text="total"></span></div>
                    <input type="file" x-ref="docInput" multiple accept="application/pdf,image/*" @change="addFiles($event.target.files); $event.target.value='';" style="display:none">
                </div>

            {{-- Zuordnung für die hochzuladenden Dateien: ALLE bekommen diese Werte. --}}
            <div style="margin-top:.9rem;font-size:.82rem;font-weight:700;opacity:.75;">Diese Dateien zuordnen zu:</div>
            <div style="display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;margin-top:.4rem;">
                <div style="min-width:200px;flex:1;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Bankkonto</label>
                    <select wire:model.live="docAccount" style="{{ $inpTxt }}">
                        @foreach ($this->accountOptions as $aid => $albl)
                            <option value="{{ $aid }}">{{ $albl }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width:120px;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Monat</label>
                    <select wire:model.live="docMonth" style="{{ $inpTxt }}">
                        @foreach ($this->monthNames() as $mn => $mlabel)
                            <option value="{{ $mn }}">{{ $mlabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width:90px;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Jahr</label>
                    <select wire:model.live="docYear" style="{{ $inpTxt }}">
                        @foreach ($this->yearOptions() as $yv => $ylabel)
                            <option value="{{ $yv }}">{{ $ylabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="min-width:170px;flex:1;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Kategorie</label>
                    <div style="display:flex;gap:.4rem;">
                        <select wire:model.live="docCategory" style="{{ $inpTxt }};flex:1;">
                            @foreach ($this->categoryOptions as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="$toggle('showNewCategory')" title="Neue Kategorie anlegen"
                            style="width:2.5rem;flex-shrink:0;border:1px solid #059669;color:#059669;background:transparent;border-radius:.5rem;font-size:1.25rem;font-weight:700;cursor:pointer;line-height:1;">+</button>
                    </div>
                    @if ($showNewCategory)
                        <div style="display:flex;gap:.4rem;margin-top:.4rem;">
                            <input type="text" wire:model="newCategoryText" wire:keydown.enter="addCategory" placeholder="Neue Kategorie …" style="{{ $inpTxt }};flex:1;">
                            <x-filament::button wire:click="addCategory" size="sm" icon="heroicon-o-plus">OK</x-filament::button>
                        </div>
                    @endif
                </div>
                <div style="min-width:200px;flex:1;">
                    <label style="display:block;font-size:.8rem;font-weight:600;margin-bottom:.25rem;">Notiz (optional)</label>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <select wire:model.live="docNote" style="{{ $inpTxt }};flex:1;">
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
                    <input type="checkbox" wire:model.live="docPrint" style="width:1.05rem;height:1.05rem;"> Drucken
                </label>
                <x-filament::button x-on:click="start()" x-bind:disabled="uploading || files.length === 0" icon="heroicon-o-arrow-up-tray">Hochladen &amp; zuordnen</x-filament::button>
            </div>
            </div>{{-- Ende docUploader --}}
            @if ($showNewNote)
                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                    <input type="text" wire:model="newNoteText" wire:keydown.enter="addNoteText" placeholder="Neuen Hinweis-Text eingeben …" style="{{ $inpTxt }};flex:1;">
                    <x-filament::button wire:click="addNoteText" icon="heroicon-o-plus">Hinzufügen</x-filament::button>
                </div>
            @endif
            <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">Viele Dateien (bis zu 500) fürs gleiche Konto/Monat? Einfach oben Konto, Monat und Kategorie setzen und alle auf einmal hochladen. Einzelne Ausreißer kannst du unten je Zeile korrigieren.</p>

            @php $docs = $this->documents; $noteTexts = $this->noteTexts; @endphp
            <div style="margin-top:1.25rem;" x-data="{ showDetails: false }">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;">
                    <div style="font-weight:600;">Hochgeladene Dateien <span style="opacity:.55;font-weight:400;font-size:.85rem;">(Reihenfolge = Bericht · ⠿ zum Ziehen)</span></div>
                    @if ($docs->isNotEmpty())
                        <button type="button" @click="showDetails = !showDetails"
                            style="border:1px solid rgba(120,120,120,.35);border-radius:.4rem;padding:.25rem .6rem;font-size:.8rem;background:transparent;cursor:pointer;white-space:nowrap;">
                            <span x-show="!showDetails">▸ Zuordnung je Zeile anzeigen</span>
                            <span x-show="showDetails" x-cloak>▾ Zuordnung ausblenden</span>
                        </button>
                    @endif
                </div>

                {{-- Filter: nur die Dateien des gewählten Konto/Monats anzeigen --}}
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;margin-bottom:.7rem;font-size:.85rem;">
                    <span style="font-weight:600;opacity:.75;">Anzeigen:</span>
                    <select wire:model.live="filterAccount" style="{{ $inpTxt }};width:auto;">
                        <option value="">Alle Konten</option>
                        @foreach ($this->accountOptions as $aid => $albl)
                            <option value="{{ $aid }}">{{ $albl }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterMonth" style="{{ $inpTxt }};width:auto;">
                        <option value="0">Alle Monate</option>
                        @foreach ($this->monthNames() as $mn => $mlabel)
                            <option value="{{ $mn }}">{{ $mlabel }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="filterYear" style="{{ $inpTxt }};width:auto;">
                        @foreach ($this->yearOptions() as $yv => $ylabel)
                            <option value="{{ $yv }}">{{ $ylabel }}</option>
                        @endforeach
                    </select>
                    <span style="opacity:.6;">· {{ $docs->count() }} Datei(en)</span>
                </div>

                @if ($docs->isNotEmpty())
                    @php $sel = 'padding:.2rem .4rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;font-size:.78rem;background:transparent;'; @endphp
                    {{-- Bulk-Leiste --}}
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;margin-bottom:.6rem;padding:.5rem .7rem;border:1px solid rgba(120,120,120,.2);border-radius:.5rem;background:rgba(120,120,120,.04);">
                        <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;">
                            <input type="checkbox" @checked(count($selectedDocs) === $docs->count() && $docs->count() > 0)
                                wire:click="toggleAllDocs($event.target.checked)"> Alle auswählen
                        </label>
                        <span style="font-size:.82rem;opacity:.7;">{{ count($selectedDocs) }} ausgewählt</span>
                        <span style="flex:1;"></span>
                        <x-filament::button wire:click="setSelectedPrint(true)" size="sm" color="gray" x-bind:disabled="{{ count($selectedDocs) === 0 ? 'true':'false' }}">Druck an</x-filament::button>
                        <x-filament::button wire:click="setSelectedPrint(false)" size="sm" color="gray" x-bind:disabled="{{ count($selectedDocs) === 0 ? 'true':'false' }}">Druck aus</x-filament::button>
                        <x-filament::button wire:click="deleteSelectedDocs" wire:confirm="Alle ausgewählten Dokumente löschen?" size="sm" color="danger" icon="heroicon-o-trash" x-bind:disabled="{{ count($selectedDocs) === 0 ? 'true':'false' }}">Löschen ({{ count($selectedDocs) }})</x-filament::button>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:.875rem;">
                        <thead>
                            <tr style="border-bottom:2px solid rgba(120,120,120,.25);text-align:left;">
                                <th style="padding:.4rem .3rem;width:26px;"></th>
                                <th style="padding:.4rem .3rem;width:26px;"></th>
                                <th style="padding:.4rem .4rem;width:36px;">Nr.</th>
                                <th style="padding:.4rem .5rem;">Datei &amp; Zuordnung</th>
                                <th style="padding:.4rem .5rem;width:60px;text-align:center;">Druck</th>
                                <th style="padding:.4rem .5rem;width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody x-data="docSorter()" x-init="init()" x-ref="rows">
                            @foreach ($docs as $i => $doc)
                                <tr wire:key="doc-{{ $doc->id }}" data-id="{{ $doc->id }}" style="border-bottom:1px solid rgba(120,120,120,.12);">
                                    <td style="padding:.5rem .3rem;vertical-align:top;text-align:center;">
                                        <span class="doc-grip" title="Ziehen zum Sortieren" style="cursor:grab;opacity:.45;font-size:1.1rem;line-height:1;user-select:none;">⠿</span>
                                    </td>
                                    <td style="padding:.5rem .3rem;vertical-align:top;text-align:center;">
                                        <input type="checkbox" value="{{ $doc->id }}" wire:model.live="selectedDocs" style="width:1.05rem;height:1.05rem;cursor:pointer;">
                                    </td>
                                    <td style="padding:.5rem .4rem;font-weight:700;vertical-align:top;">{{ $i + 1 }}</td>
                                    <td style="padding:.5rem .5rem;">
                                        <a href="{{ $doc->preview_url }}" target="_blank" style="color:#059669;text-decoration:underline;font-weight:500;">{{ $doc->file_name ?: 'Datei' }}</a>
                                        {{-- Kompakt: Zuordnung als Text (wenn Details ausgeblendet) --}}
                                        <div x-show="!showDetails" style="font-size:.78rem;opacity:.7;margin-top:.15rem;">
                                            {{ $doc->bankAccount?->label }} · {{ $this->monthNames()[(int) $doc->period->month] ?? '' }} {{ $doc->period->year }} · {{ $doc->category }}@if ($doc->note) · 📝 {{ \Illuminate\Support\Str::limit($doc->note, 45) }}@endif
                                        </div>
                                        {{-- Detail: Dropdowns zum Ändern (Konto · Monat · Jahr · Kategorie · Notiz) --}}
                                        <div x-show="showDetails" x-cloak style="margin-top:.35rem;">
                                        <div style="display:flex;flex-wrap:wrap;gap:.35rem;">
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
                                            <select wire:change="setDocCategory({{ $doc->id }}, $event.target.value)" title="Kategorie" style="{{ $sel }};min-width:130px;">
                                                @foreach ($this->categoryOptions as $c)
                                                    <option value="{{ $c }}" @selected($doc->category === $c)>{{ $c }}</option>
                                                @endforeach
                                                @if ($doc->category && ! in_array($doc->category, $this->categoryOptions, true))
                                                    <option value="{{ $doc->category }}" selected>{{ $doc->category }}</option>
                                                @endif
                                            </select>
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
                                        </div>{{-- Ende Detail-Container --}}
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
                    <p style="font-size:.78rem;opacity:.6;margin-top:.5rem;">Reihenfolge per ⠿ ziehen (bestimmt die Nummerierung im Bericht je Konto &amp; Monat). Ausreißer je Zeile über die Dropdowns korrigieren.</p>
                @else
                    <div style="font-size:.85rem;opacity:.6;">Keine Dateien für diese Auswahl. Filter oben ändern (z. B. „Alle Monate") oder Dateien hochladen.</div>
                @endif
            </div>
        </x-filament::section>

    </div>

    @assets
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.6/Sortable.min.js"></script>
    @endassets

    @script
        <script>
            // Häppchen-Upload: lädt viele Dateien in Portionen à 15 hoch, damit die
            // Server-Grenze max_file_uploads (all-inkl = 20) nie überschritten wird.
            Alpine.data('docUploader', () => ({
                dragging: false,
                files: [],
                uploading: false,
                done: 0,
                total: 0,
                addFiles(fileList) {
                    for (const f of fileList) { this.files.push(f); }
                },
                async start() {
                    if (this.uploading || this.files.length === 0) { return; }
                    this.uploading = true;
                    const all = this.files.slice();
                    this.files = [];
                    this.total = all.length;
                    this.done = 0;
                    const CHUNK = 15;
                    try {
                        for (let i = 0; i < all.length; i += CHUNK) {
                            const chunk = all.slice(i, i + CHUNK);
                            await this.uploadChunk(chunk);
                            this.done += chunk.length;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    this.uploading = false;
                },
                uploadChunk(chunk) {
                    return new Promise((resolve, reject) => {
                        this.$wire.uploadMultiple('docUploads', chunk,
                            () => { this.$wire.uploadDocuments().then(resolve).catch(reject); },
                            () => reject(new Error('upload fehlgeschlagen')),
                        );
                    });
                },
            }));

            Alpine.data('docSorter', () => ({
                init() {
                    if (! window.Sortable || ! this.$refs.rows) { return; }
                    window.Sortable.create(this.$refs.rows, {
                        handle: '.doc-grip',
                        animation: 150,
                        onEnd: () => {
                            const ids = [...this.$refs.rows.querySelectorAll('tr[data-id]')].map((r) => r.dataset.id);
                            this.$wire.reorderDocuments(ids);
                        },
                    });
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
