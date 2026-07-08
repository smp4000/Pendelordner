<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
    @endphp

    <div style="display:flex;flex-direction:column;gap:1.25rem;">

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
                            <a href="{{ $r->preview_url }}" target="_blank" style="font-size:.78rem;color:#059669;">Vorschau öffnen ↗</a>
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
                                <a href="{{ $d->preview_url }}" target="_blank" style="font-size:.78rem;color:#059669;">Vorschau öffnen ↗</a>
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
                                    <a href="{{ $d->duplicateOf->preview_url }}" target="_blank" style="font-size:.78rem;color:#059669;">Vorschau öffnen ↗</a>
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

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
