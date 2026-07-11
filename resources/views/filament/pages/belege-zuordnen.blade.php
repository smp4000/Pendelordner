<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
    @endphp

    @php $previewReceipt = $this->previewReceipt; @endphp

    {{-- Ist rechts eine Belegvorschau offen, wird der Inhalt links per
         padding-right weggeschoben, damit die feste Vorschau ihn nicht
         verdeckt. --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;transition:padding .2s ease;{{ $previewReceipt ? 'padding-right:520px;' : '' }}">

        {{-- Upload --}}
        <x-filament::section>
            <x-slot name="heading">Belege hochladen</x-slot>

            <div style="display:flex;gap:.25rem;margin-bottom:.75rem;flex-wrap:wrap;">
                @foreach (['incoming_invoice'=>'Rechnungseingang','outgoing_invoice'=>'Rechnungsausgang','cash'=>'Kasse','other'=>'Sonstige'] as $val => $lbl)
                    <button wire:click="$set('uploadType','{{ $val }}')"
                        style="padding:.35rem .7rem;border:1px solid rgba(120,120,120,.3);border-radius:.4rem;cursor:pointer;font-size:.8rem;{{ $uploadType===$val ? 'background:#10b981;color:#fff;border-color:#10b981;' : 'background:transparent;' }}">{{ $lbl }}</button>
                @endforeach
            </div>

            <div x-data="{ dragging:false }"
                x-on:dragover.prevent="dragging=true"
                x-on:dragleave.prevent="dragging=false"
                x-on:drop.prevent="dragging=false; $refs.fileInput.files=$event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change',{bubbles:true}))"
                :style="dragging ? 'background:rgba(16,185,129,.08);' : ''"
                style="border:2px dashed #10b981;border-radius:.6rem;padding:2rem;text-align:center;">
                <input type="file" multiple x-ref="fileInput" wire:model="uploadFiles" style="display:none" id="belegeUpload"
                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,application/pdf,image/*">
                <div style="font-size:2rem;color:#10b981;">＋</div>
                <p style="margin:.5rem 0;font-weight:600;">Belege hier ablegen (mehrere möglich) oder</p>
                <label for="belegeUpload" style="display:inline-block;padding:.45rem .9rem;background:#10b981;color:#fff;border-radius:.4rem;cursor:pointer;">Dateien auswählen</label>
                <p style="margin-top:.5rem;font-size:.75rem;opacity:.6;">PDF, JPG, PNG, TIFF · max. 20 MB je Datei</p>

                <div wire:loading wire:target="uploadFiles" style="margin-top:.5rem;font-size:.8rem;opacity:.7;">Lädt hoch…</div>

                @if (! empty($uploadFiles))
                    <div style="margin-top:.75rem;font-size:.85rem;">{{ count($uploadFiles) }} Datei(en) gewählt</div>
                    <div style="margin-top:.5rem;">
                        <x-filament::button wire:click="uploadReceipts" wire:loading.attr="disabled" wire:target="uploadReceipts" icon="heroicon-o-arrow-up-tray" color="primary">
                            Hochladen & OCR
                        </x-filament::button>
                        <span wire:loading wire:target="uploadReceipts" style="margin-left:.5rem;font-size:.8rem;opacity:.7;">Verarbeite…</span>
                    </div>
                @endif
                @error('uploadFiles') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
                @error('uploadFiles.*') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
            </div>
        </x-filament::section>

        {{-- Nicht zugeordnete Belege + Umsatz-Vorschlag --}}
        <x-filament::section style="padding:0;overflow:hidden;">
            <div style="padding:.6rem .8rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">
                Nicht zugeordnete Belege ({{ $this->unassignedReceipts->count() }})
            </div>

            <div style="display:grid;grid-template-columns:1.4fr 1.6fr auto;gap:0;font-size:.85rem;font-weight:600;opacity:.7;padding:.5rem .8rem;border-bottom:1px solid rgba(120,120,120,.15);">
                <div>Beleg</div><div>Vorgeschlagener Umsatz</div><div></div>
            </div>

            @forelse ($this->unassignedReceipts as $r)
                @php $sug = $this->suggestionFor($r); @endphp
                <div style="display:grid;grid-template-columns:1.4fr 1.6fr auto;gap:.5rem;align-items:center;padding:.6rem .8rem;border-bottom:1px solid rgba(120,120,120,.12);">
                    {{-- Beleg --}}
                    <div>
                        <div style="font-weight:500;">{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}</div>
                        <div style="font-size:.8rem;opacity:.65;">
                            {{ $r->supplier?->name }}
                            @if ($r->invoice_date) · {{ $r->invoice_date->format('d.m.Y') }} @endif
                            @if ($r->gross_amount) · {{ $money($r->gross_amount) }} @endif
                        </div>
                        @if ($r->preview_url)
                            <div style="display:flex;gap:.7rem;align-items:center;">
                                <button type="button" wire:click="preview({{ $r->id }})"
                                    style="font-size:.78rem;color:{{ $previewReceipt?->id === $r->id ? '#047857' : '#059669' }};font-weight:{{ $previewReceipt?->id === $r->id ? '700' : '400' }};background:none;border:0;cursor:pointer;padding:0;text-decoration:underline;">Vorschau ▸</button>
                                <a href="{{ $r->preview_url }}" target="_blank" title="In neuem Tab öffnen" style="font-size:.78rem;color:#059669;opacity:.65;">↗</a>
                            </div>
                        @endif

                        {{-- Lieferant/Tankstelle/Kundennummer: erkannt anzeigen, sonst vorbefülltes Anlege-Modal --}}
                        <div style="margin-top:.35rem;display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;">
                            @if ($r->supplier)
                                <span style="font-size:.78rem;padding:.1rem .45rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;">
                                    Lieferant: {{ $r->supplier->display_name ?: $r->supplier->name }}
                                </span>
                            @else
                                <x-filament::button size="xs" color="warning" icon="heroicon-o-user-plus"
                                    wire:click="mountAction('createSupplier', { receipt: {{ $r->id }} })">
                                    Lieferant anlegen
                                </x-filament::button>
                            @endif
                            @if ($r->business)
                                <span style="font-size:.78rem;padding:.1rem .45rem;border-radius:.3rem;background:rgba(99,102,241,.15);color:#4f46e5;">
                                    {{ $r->business->display_label }}
                                </span>
                            @elseif ($r->supplier)
                                <span style="font-size:.78rem;padding:.1rem .45rem;border-radius:.3rem;background:rgba(245,158,11,.18);color:#b45309;" title="Tankstelle nicht eindeutig – Kundennummer beim Lieferanten prüfen">
                                    Tankstelle ?
                                </span>
                            @endif
                            @if ($r->customer_number)
                                <span style="font-size:.78rem;padding:.1rem .45rem;border-radius:.3rem;background:rgba(120,120,120,.15);opacity:.85;">
                                    Kd.-Nr. {{ $r->customer_number }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Vorschlag --}}
                    <div>
                        @if ($sug)
                            <div>{{ $sug['transaction']->counterparty ?: 'Umsatz #' . $sug['transaction']->id }}</div>
                            <div style="font-size:.8rem;opacity:.65;">
                                {{ $sug['transaction']->booking_date?->format('d.m.Y') }} ·
                                <span style="color:{{ $sug['transaction']->amount < 0 ? '#dc2626' : '#059669' }};">{{ $money($sug['transaction']->amount) }}</span>
                                <span style="margin-left:.3rem;padding:.05rem .4rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;font-size:.72rem;">{{ $sug['score'] }} %</span>
                            </div>
                        @else
                            <span style="opacity:.5;">Kein passender Umsatz gefunden</span>
                        @endif
                    </div>

                    {{-- Aktion --}}
                    <div style="text-align:right;">
                        @if ($sug)
                            <x-filament::button wire:click="assign({{ $r->id }}, {{ $sug['transaction']->id }})" size="sm" color="primary">
                                Zuordnen
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <p style="padding:1rem;font-size:.85rem;opacity:.6;">Keine offenen Belege – alle sind zugeordnet. 🎉</p>
            @endforelse
        </x-filament::section>

        {{-- Mögliche Dubletten (isoliert, warten auf Entscheidung) --}}
        @php $dups = $this->duplicateSuspects; @endphp
        @if ($dups->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Mögliche Dubletten ({{ $dups->count() }})</x-slot>
                <x-slot name="description">Gleiche Rechnungsnummer wie ein vorhandener Beleg (andere Datei). Diese Belege sind isoliert – sie erscheinen in keiner Zuordnung, bis du entscheidest.</x-slot>

                {{-- Mehrfachauswahl: mehrere Dubletten auf einmal löschen/behalten. --}}
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.6rem;margin-bottom:.6rem;padding:.5rem .7rem;border:1px solid rgba(120,120,120,.2);border-radius:.5rem;background:rgba(120,120,120,.04);">
                    <label style="display:flex;align-items:center;gap:.4rem;font-size:.85rem;cursor:pointer;">
                        <input type="checkbox"
                            @checked(count($selectedDuplicates) === $dups->count() && $dups->count() > 0)
                            wire:click="toggleAllDuplicates($event.target.checked)">
                        Alle auswählen
                    </label>
                    <span style="font-size:.82rem;opacity:.7;">{{ count($selectedDuplicates) }} ausgewählt</span>
                    <span style="flex:1;"></span>
                    <x-filament::button wire:click="deleteSelectedDuplicates"
                        wire:confirm="Alle ausgewählten Dubletten endgültig löschen?"
                        size="sm" color="danger" icon="heroicon-o-trash"
                        x-bind:disabled="{{ count($selectedDuplicates) === 0 ? 'true' : 'false' }}">
                        Ausgewählte löschen ({{ count($selectedDuplicates) }})
                    </x-filament::button>
                    <x-filament::button wire:click="keepSelectedDuplicates" size="sm" color="gray"
                        x-bind:disabled="{{ count($selectedDuplicates) === 0 ? 'true' : 'false' }}">
                        Ausgewählte behalten
                    </x-filament::button>
                </div>

                @foreach ($dups as $d)
                    <div style="display:grid;grid-template-columns:auto 1.4fr 1.4fr auto;gap:.5rem;align-items:center;padding:.6rem .8rem;border-bottom:1px solid rgba(120,120,120,.12);background:rgba(254,243,199,.25);border-left:3px solid #f59e0b;">
                        {{-- Auswahl für Mehrfach-Aktion --}}
                        <input type="checkbox" value="{{ $d->id }}" wire:model.live="selectedDuplicates"
                            title="Für Mehrfachauswahl markieren" style="width:1.1rem;height:1.1rem;cursor:pointer;">
                        {{-- Neuer (isolierter) Beleg --}}
                        <div>
                            <div style="font-size:.72rem;font-weight:700;color:#b45309;margin-bottom:.15rem;">NEU (isoliert)</div>
                            <div style="font-weight:500;">{{ $d->invoice_number ?: ('Beleg #' . $d->id) }}</div>
                            <div style="font-size:.8rem;opacity:.65;">
                                {{ $d->supplier?->name }}
                                @if ($d->invoice_date) · {{ $d->invoice_date->format('d.m.Y') }} @endif
                                @if ($d->gross_amount) · {{ $money($d->gross_amount) }} @endif
                                · {{ $d->file_name }}
                            </div>
                            @if ($d->preview_url)
                                <div style="display:flex;gap:.7rem;align-items:center;">
                                    <button type="button" wire:click="preview({{ $d->id }})"
                                        style="font-size:.78rem;color:{{ $previewReceipt?->id === $d->id ? '#047857' : '#059669' }};font-weight:{{ $previewReceipt?->id === $d->id ? '700' : '400' }};background:none;border:0;cursor:pointer;padding:0;text-decoration:underline;">Vorschau ▸</button>
                                    <a href="{{ $d->preview_url }}" target="_blank" title="In neuem Tab öffnen" style="font-size:.78rem;color:#059669;opacity:.65;">↗</a>
                                </div>
                            @endif
                        </div>
                        {{-- Original --}}
                        <div>
                            <div style="font-size:.72rem;font-weight:700;opacity:.6;margin-bottom:.15rem;">ORIGINAL (bleibt)</div>
                            @if ($d->duplicateOf)
                                <div style="font-weight:500;">{{ $d->duplicateOf->invoice_number ?: ('Beleg #' . $d->duplicateOf->id) }}</div>
                                <div style="font-size:.8rem;opacity:.65;">
                                    @if ($d->duplicateOf->invoice_date) {{ $d->duplicateOf->invoice_date->format('d.m.Y') }} · @endif
                                    @if ($d->duplicateOf->gross_amount) {{ $money($d->duplicateOf->gross_amount) }} · @endif
                                    {{ $d->duplicateOf->file_name }}
                                </div>
                                @if ($d->duplicateOf->preview_url)
                                    <div style="display:flex;gap:.7rem;align-items:center;">
                                        <button type="button" wire:click="preview({{ $d->duplicateOf->id }})"
                                            style="font-size:.78rem;color:{{ $previewReceipt?->id === $d->duplicateOf->id ? '#047857' : '#059669' }};font-weight:{{ $previewReceipt?->id === $d->duplicateOf->id ? '700' : '400' }};background:none;border:0;cursor:pointer;padding:0;text-decoration:underline;">Vorschau ▸</button>
                                        <a href="{{ $d->duplicateOf->preview_url }}" target="_blank" title="In neuem Tab öffnen" style="font-size:.78rem;color:#059669;opacity:.65;">↗</a>
                                    </div>
                                @endif
                            @else
                                <span style="opacity:.5;">Original nicht mehr vorhanden</span>
                            @endif
                        </div>
                        {{-- Entscheidung --}}
                        <div style="display:flex;flex-direction:column;gap:.35rem;text-align:right;">
                            <x-filament::button wire:click="deleteDuplicate({{ $d->id }})" wire:confirm="Diese Dublette endgültig löschen?" size="sm" color="danger" icon="heroicon-o-trash">
                                Dublette löschen
                            </x-filament::button>
                            <x-filament::button wire:click="keepDuplicate({{ $d->id }})" size="sm" color="gray">
                                Kein Duplikat – behalten
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </x-filament::section>
        @endif

        {{-- Rechte Belegvorschau (gleicher Inline-Viewer wie in den
             Kontoumsatzdetails: PDF via PDF.js, Zoom, Lupe, Drucken). Fest am
             rechten Rand, volle Höhe. Der Inhalt links wird per padding-right
             weggeschoben. --}}
        @if ($previewReceipt && $previewReceipt->preview_url)
            @php
                $btn = 'display:inline-flex;align-items:center;justify-content:center;width:1.9rem;height:1.9rem;border-radius:.4rem;border:1px solid rgba(120,120,120,.3);background:transparent;cursor:pointer;font-size:1rem;line-height:1;text-decoration:none;color:inherit;';
                $vorschauLabel = $previewReceipt->invoice_number ?: ($previewReceipt->file_name ?: ('Beleg #' . $previewReceipt->id));
            @endphp
            <div class="beleg-scroll"
                style="position:fixed;top:0;right:0;width:500px;height:100vh;z-index:40;overflow:auto;background:var(--fi-color-white,#fff);border-left:1px solid rgba(120,120,120,.25);box-shadow:-6px 0 20px rgba(0,0,0,.12);">
                <div wire:key="preview-{{ $previewReceipt->id }}"
                    x-data="receiptViewer(@js($previewReceipt->preview_url), {{ $previewReceipt->is_pdf ? 'true' : 'false' }})" x-init="load()">

                    {{-- Kopfleiste (bleibt beim Scrollen oben): Titel + Zoom/Lupe/
                         Drucken/Neuer Tab/Schließen. --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.4rem .6rem;border-bottom:1px solid rgba(120,120,120,.2);position:sticky;top:0;background:var(--fi-color-white,#fff);z-index:3;">
                        <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $vorschauLabel }}</span>
                        <span style="display:flex;align-items:center;gap:.3rem;flex-shrink:0;">
                            <button type="button" @click="zoomOut()" title="Verkleinern" style="{{ $btn }}">−</button>
                            <span x-text="Math.round(zoom*100)+' %'" style="min-width:3rem;text-align:center;font-size:.78rem;opacity:.75;"></span>
                            <button type="button" @click="zoomIn()" title="Vergrößern" style="{{ $btn }}">+</button>
                            <button type="button" @click="reset()" title="Originalgröße" style="{{ $btn }}">⟲</button>
                            <button type="button" @click="lens = !lens" title="Lupe (beim Drüberfahren vergrößern)"
                                :style="'{{ $btn }}' + (lens ? 'background:#0ea5e9;color:#fff;border-color:#0ea5e9;' : '')">🔍</button>
                            <button type="button" @click="printReceipt()" title="Drucken" style="{{ $btn }}">🖨</button>
                            <a :href="url" target="_blank" title="In neuem Tab öffnen" style="{{ $btn }}">↗</a>
                            <button type="button" wire:click="closePreview" title="Schließen" style="{{ $btn }}">✕</button>
                        </span>
                    </div>

                    <div style="padding:.5rem;position:relative;">
                        @if ($previewReceipt->is_pdf)
                            {{-- Inline-PDF (PDF.js), alle Seiten untereinander. --}}
                            <div class="beleg-scroll" style="overflow-x:auto;background:#fff;border:1px solid rgba(120,120,120,.2);border-radius:.5rem;"
                                :style="lens ? 'cursor:none;' : ''"
                                @mousemove="magnify($event)" @mouseleave="lensVisible=false">
                                <div wire:ignore x-ref="pages" style="padding:10px;text-align:center;"></div>
                                <template x-if="error">
                                    <div style="color:#374151;padding:1rem;text-align:center;">
                                        Inline-Vorschau nicht möglich.
                                        <a :href="url" target="_blank" style="color:#059669;text-decoration:underline;">Im neuen Tab öffnen</a>
                                    </div>
                                </template>
                            </div>
                        @else
                            <div class="beleg-scroll" style="overflow-x:auto;text-align:center;background:#fff;border:1px solid rgba(120,120,120,.2);border-radius:.5rem;"
                                :style="lens ? 'cursor:none;' : ''"
                                @mousemove="magnify($event)" @mouseleave="lensVisible=false">
                                <img :src="url" alt="Beleg" :style="`width:${Math.round(zoom*100)}%;max-width:none;object-fit:contain;`"
                                    style="border-radius:.5rem;">
                            </div>
                        @endif

                        {{-- Lupe: folgt dem Cursor. --}}
                        <canvas x-ref="lens" x-show="lens && lensVisible" width="240" height="180"
                            style="position:fixed;pointer-events:none;z-index:9999;width:240px;height:180px;border:2px solid #0ea5e9;border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.3);"></canvas>
                    </div>
                </div>
            </div>
        @endif

    </div>

    <x-filament-actions::modals />

    {{-- PDF.js einmalig laden (für die Inline-Vorschau). --}}
    @assets
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    @endassets

    {{-- Immer sichtbarer Scrollbalken für die Vorschau. --}}
    <style>
        .beleg-scroll { scrollbar-width: thin; scrollbar-color: #9ca3af transparent; }
        .beleg-scroll::-webkit-scrollbar { width: 14px; height: 14px; }
        .beleg-scroll::-webkit-scrollbar-thumb { background: #9ca3af; border-radius: 8px; border: 3px solid #fff; }
        .beleg-scroll::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .beleg-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px; }
    </style>

    {{-- Inline-PDF-Viewer (identisch zu den Kontoumsatzdetails). --}}
    @script
        <script>
            Alpine.data('receiptViewer', (url, isPdf) => {
                // pdf.js-Dokument NICHT reaktiv halten (Alpine-Proxy bricht private Felder).
                let pdfDoc = null;
                let renderRun = 0;

                return {
                    url: url,
                    isPdf: isPdf,
                    error: false,
                    zoom: 1,
                    baseScale: 1.4,
                    loadStarted: false,
                    lens: false,
                    lensVisible: false,
                    lensZoom: 2.6,
                    init() {
                        if (this.isPdf) { this.load(); }
                    },
                    async load() {
                        if (!this.isPdf || this.loadStarted) { return; }
                        this.loadStarted = true;
                        try {
                            let tries = 0;
                            while (!window.pdfjsLib && tries < 160) {
                                await new Promise((r) => setTimeout(r, 50));
                                tries++;
                            }
                            if (!window.pdfjsLib) { this.error = true; return; }

                            window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                                'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                            pdfDoc = await window.pdfjsLib.getDocument(this.url).promise;
                            await this.renderPdf();
                        } catch (e) {
                            console.error(e);
                            this.error = true;
                        }
                    },
                    async renderPdf() {
                        if (!pdfDoc) { return; }
                        const container = this.$refs.pages;
                        if (!container) { return; }
                        const run = ++renderRun;
                        container.innerHTML = '';
                        const scale = this.baseScale * this.zoom;
                        for (let i = 1; i <= pdfDoc.numPages; i++) {
                            if (run !== renderRun) { return; }
                            const page = await pdfDoc.getPage(i);
                            const viewport = page.getViewport({ scale: scale });
                            const canvas = document.createElement('canvas');
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            canvas.style.display = 'block';
                            canvas.style.margin = '10px auto';
                            canvas.style.maxWidth = this.zoom <= 1 ? '100%' : 'none';
                            canvas.style.background = '#fff';
                            canvas.style.boxShadow = '0 1px 4px rgba(0,0,0,.35)';
                            if (run !== renderRun) { return; }
                            container.appendChild(canvas);
                            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                        }
                    },
                    zoomIn() {
                        this.zoom = Math.min(4, +(this.zoom + 0.2).toFixed(2));
                        if (this.isPdf) { this.renderPdf(); }
                    },
                    zoomOut() {
                        this.zoom = Math.max(0.4, +(this.zoom - 0.2).toFixed(2));
                        if (this.isPdf) { this.renderPdf(); }
                    },
                    reset() {
                        this.zoom = 1;
                        if (this.isPdf) { this.renderPdf(); }
                    },
                    printReceipt() {
                        const w = window.open(this.url, '_blank');
                        if (w) {
                            try { w.addEventListener('load', () => w.print()); } catch (e) { /* Popup evtl. blockiert */ }
                        }
                    },
                    magnify(e) {
                        if (!this.lens) { this.lensVisible = false; return; }
                        const src = e.target;
                        if (!src || (src.tagName !== 'CANVAS' && src.tagName !== 'IMG')) {
                            this.lensVisible = false;
                            return;
                        }
                        const lensEl = this.$refs.lens;
                        if (!lensEl) { return; }

                        const rect = src.getBoundingClientRect();
                        const srcW = src.tagName === 'IMG' ? (src.naturalWidth || rect.width) : src.width;
                        const srcH = src.tagName === 'IMG' ? (src.naturalHeight || rect.height) : src.height;

                        const fx = (e.clientX - rect.left) / rect.width;
                        const fy = (e.clientY - rect.top) / rect.height;
                        const cx = fx * srcW;
                        const cy = fy * srcH;

                        const lw = lensEl.width, lh = lensEl.height;
                        const regionW = (lw / this.lensZoom) * (srcW / rect.width);
                        const regionH = (lh / this.lensZoom) * (srcH / rect.height);

                        const ctx = lensEl.getContext('2d');
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, lw, lh);
                        try {
                            ctx.drawImage(src, cx - regionW / 2, cy - regionH / 2, regionW, regionH, 0, 0, lw, lh);
                        } catch (err) { return; }

                        let px = e.clientX + 18, py = e.clientY + 18;
                        if (px + lw > window.innerWidth) { px = e.clientX - lw - 18; }
                        if (py + lh > window.innerHeight) { py = e.clientY - lh - 18; }
                        lensEl.style.left = px + 'px';
                        lensEl.style.top = py + 'px';
                        this.lensVisible = true;
                    },
                };
            });
        </script>
    @endscript
</x-filament-panels::page>
