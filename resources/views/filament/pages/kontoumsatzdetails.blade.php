<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $tx = $this->selectedTransaction;
        $receipt = $this->selectedReceipt;
        $statusColor = fn ($s) => match ($s) {
            \App\Enums\TransactionStatus::Open => '#ef4444',
            \App\Enums\TransactionStatus::PartiallyAllocated => '#f59e0b',
            default => '#10b981',
        };
        $rowBase = 'display:flex;justify-content:space-between;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);cursor:pointer;';
        $tabBtn = fn ($active) => 'padding:.45rem .8rem;border:0;background:none;cursor:pointer;font-size:.85rem;border-bottom:2px solid '
            . ($active ? '#10b981;font-weight:600;color:#059669;' : 'transparent;opacity:.7;');
    @endphp

    @php
        $navBtn = 'padding:.25rem .5rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;background:transparent;cursor:pointer;font-size:.9rem;line-height:1;';
    @endphp

    {{-- Kompakte Navigation (Umsatz X von Y) --}}
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:.4rem;margin-bottom:.75rem;">
        <button wire:click="goTo('first')" style="{{ $navBtn }}" title="Erster">&#124;&#9664;</button>
        <button wire:click="goTo('prev')" style="{{ $navBtn }}" title="Zurück">&#9664;</button>
        <span style="font-size:.85rem;min-width:9rem;text-align:center;">Umsatz <strong>{{ $this->position }}</strong> von {{ $this->total }}</span>
        <button wire:click="goTo('next')" style="{{ $navBtn }}" title="Weiter">&#9654;</button>
        <button wire:click="goTo('last')" style="{{ $navBtn }}" title="Letzter">&#9654;&#124;</button>
    </div>

    <div style="display:grid;grid-template-columns:2.5fr 5fr 4.5fr;gap:1rem;align-items:start;">

        {{-- LINKS: offene Umsätze --}}
        <x-filament::section style="padding:0;overflow:hidden;">
            <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">
                Offene Umsätze ({{ $this->openTransactions->count() }})
            </div>
            <div style="max-height:72vh;overflow-y:auto;">
                @forelse ($this->openTransactions as $row)
                    <div wire:click="selectTransaction({{ $row->id }})"
                        style="{{ $rowBase }}flex-direction:column;border-left:4px solid {{ $statusColor($row->status) }};{{ $row->id === $this->selectedTransactionId ? 'background:rgba(16,185,129,.12);' : '' }}">
                        <div style="display:flex;justify-content:space-between;width:100%;">
                            <span style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%;">{{ $row->counterparty ?: 'Unbekannt' }}</span>
                            <span style="white-space:nowrap;color:{{ $row->amount < 0 ? '#dc2626' : '#059669' }};">{{ $money($row->amount) }}</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;width:100%;font-size:.75rem;opacity:.6;">
                            <span>{{ $row->booking_date?->format('d.m.Y') }}</span>
                            <span>{{ $row->receipts->count() }} Beleg(e)</span>
                        </div>
                    </div>
                @empty
                    <p style="padding:1rem;opacity:.6;">Keine offenen Umsätze 🎉</p>
                @endforelse
            </div>
        </x-filament::section>

        {{-- MITTE: Details + Tabs --}}
        <div style="display:flex;flex-direction:column;gap:1rem;">
            @if ($tx)
                {{-- Umsatzdetails --}}
                <x-filament::section>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                        <div>
                            <div style="font-size:1.1rem;font-weight:600;">{{ $tx->counterparty ?: 'Bankumsatz' }}</div>
                            <div style="font-size:.85rem;opacity:.6;">{{ $tx->booking_date?->format('d.m.Y') }} · {{ $tx->bankAccount?->label }}</div>
                        </div>
                        <div style="font-size:1.25rem;font-weight:700;white-space:nowrap;color:{{ $tx->amount < 0 ? '#dc2626' : '#059669' }};">{{ $money($tx->amount) }}</div>
                    </div>
                    @if ($tx->purpose)
                        <p style="margin-top:.5rem;font-size:.8rem;opacity:.75;">{{ \Illuminate\Support\Str::limit($tx->purpose, 140) }}</p>
                    @endif
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.6rem;font-size:.85rem;">
                        <div><span style="opacity:.6;">Kategorie:</span> {{ $tx->category?->name ?? '—' }}</div>
                        <div><span style="opacity:.6;">Kostenstelle:</span> {{ $tx->costCenter?->name ?? '—' }}</div>
                        <div><span style="opacity:.6;">Status:</span> {{ $tx->status->getLabel() }}</div>
                        <div><span style="opacity:.6;">Differenz:</span>
                            <span style="color:{{ abs($tx->difference) < 0.01 ? '#059669' : '#d97706' }};">{{ $money($tx->difference) }}</span></div>
                    </div>
                    <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap;">
                        <x-filament::button wire:click="markReviewed" icon="heroicon-o-shield-check" color="success" size="sm">Als geprüft markieren</x-filament::button>
                        <x-filament::button wire:click="togglePaid" icon="heroicon-o-banknotes" :color="$tx->fully_paid ? 'success' : 'gray'" size="sm">
                            {{ $tx->fully_paid ? 'Vollständig bezahlt ✓' : 'Als bezahlt markieren' }}
                        </x-filament::button>
                        <x-filament::button tag="a" href="{{ \App\Filament\Resources\BankTransactions\BankTransactionResource::getUrl('edit', ['record' => $tx]) }}" icon="heroicon-o-pencil-square" color="gray" size="sm">Bearbeiten</x-filament::button>
                    </div>
                </x-filament::section>

                {{-- Tabs --}}
                <x-filament::section style="padding:0;overflow:hidden;">
                    <div style="display:flex;gap:.25rem;border-bottom:1px solid rgba(120,120,120,.2);padding:0 .5rem;flex-wrap:wrap;">
                        <button wire:click="setTab('assigned')" style="{{ $tabBtn($activeTab==='assigned') }}">Zugeordnete Belege ({{ $tx->receipts->count() }})</button>
                        <button wire:click="setTab('suggestions')" style="{{ $tabBtn($activeTab==='suggestions') }}">Vorschläge ({{ $this->suggestions->count() }})</button>
                        <button wire:click="setTab('search')" style="{{ $tabBtn($activeTab==='search') }}">Manuelle Belegsuche</button>
                        <button wire:click="setTab('upload')" style="{{ $tabBtn($activeTab==='upload') }}">Beleg hochladen</button>
                    </div>

                    <div style="padding:.5rem;">
                        {{-- TAB: Zugeordnete Belege --}}
                        @if ($activeTab === 'assigned')
                            @forelse ($tx->receipts as $r)
                                <div wire:click="selectReceipt({{ $r->id }})"
                                    style="{{ $rowBase }}align-items:center;{{ $r->id === $this->selectedReceiptId ? 'background:rgba(16,185,129,.12);' : '' }}">
                                    <span>{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}
                                        <span style="opacity:.6;">· {{ $r->supplier?->name }}</span></span>
                                    <span style="display:flex;gap:.75rem;align-items:center;white-space:nowrap;">
                                        {{ $money($r->pivot->amount) }}
                                        <button type="button" wire:click.stop="detachReceipt({{ $r->id }})" style="color:#dc2626;background:none;border:none;cursor:pointer;">lösen</button>
                                    </span>
                                </div>
                            @empty
                                <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Noch kein Beleg zugeordnet.</p>
                            @endforelse

                        {{-- TAB: Vorschläge --}}
                        @elseif ($activeTab === 'suggestions')
                            @forelse ($this->suggestions as $s)
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);">
                                    <span wire:click="selectReceipt({{ $s['receipt']->id }})" style="cursor:pointer;">
                                        {{ $s['receipt']->invoice_number ?: ('Beleg #' . $s['receipt']->id) }}
                                        <span style="opacity:.6;">· {{ $s['receipt']->supplier?->name }} · {{ $money($s['receipt']->gross_amount) }}</span>
                                        <span style="margin-left:.4rem;padding:.05rem .4rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;font-size:.75rem;">{{ $s['score'] }} %</span>
                                    </span>
                                    <x-filament::button wire:click="attachReceipt({{ $s['receipt']->id }})" size="sm" color="primary">Zuordnen</x-filament::button>
                                </div>
                            @empty
                                <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Keine Vorschläge gefunden.</p>
                            @endforelse

                        {{-- TAB: Manuelle Belegsuche --}}
                        @elseif ($activeTab === 'search')
                            <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-bottom:.5rem;">
                                <div style="flex:1;min-width:12rem;">
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model.live.debounce.400ms="searchQuery" placeholder="Lieferant, Rechnungs-Nr., Text …" />
                                    </x-filament::input.wrapper>
                                </div>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="searchAssigned">
                                        <option value="unassigned">Nicht zugeordnet</option>
                                        <option value="assigned">Zugeordnet</option>
                                        <option value="all">Alle</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="searchPaid">
                                        <option value="all">Bezahlt: Alle</option>
                                        <option value="paid">Bezahlt</option>
                                        <option value="unpaid">Offen</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select wire:model.live="searchType">
                                        <option value="all">Belegtyp: Alle</option>
                                        <option value="incoming_invoice">Rechnungseingang</option>
                                        <option value="outgoing_invoice">Rechnungsausgang</option>
                                        <option value="cash">Kasse</option>
                                        <option value="other">Sonstige</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </div>

                            @forelse ($this->searchResults as $r)
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);">
                                    <span wire:click="selectReceipt({{ $r->id }})" style="cursor:pointer;">
                                        {{ $r->invoice_number ?: ('Beleg #' . $r->id) }}
                                        <span style="opacity:.6;">· {{ $r->supplier?->name }} · {{ $r->invoice_date?->format('d.m.Y') }} · {{ $money($r->gross_amount) }}</span>
                                    </span>
                                    <x-filament::button wire:click="attachReceipt({{ $r->id }})" size="sm" color="primary">Zuordnen</x-filament::button>
                                </div>
                            @empty
                                <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Keine Belege gefunden.</p>
                            @endforelse

                        {{-- TAB: Beleg hochladen (Upload online) --}}
                        @elseif ($activeTab === 'upload')
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
                                <input type="file" x-ref="fileInput" wire:model="uploadFile" style="display:none" id="belegUpload"
                                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,application/pdf,image/*">
                                <div style="font-size:2rem;color:#10b981;">＋</div>
                                <p style="margin:.5rem 0;font-weight:600;">Beleg hier ablegen oder</p>
                                <label for="belegUpload" style="display:inline-block;padding:.45rem .9rem;background:#10b981;color:#fff;border-radius:.4rem;cursor:pointer;">Datei auswählen</label>
                                <p style="margin-top:.5rem;font-size:.75rem;opacity:.6;">PDF, JPG, PNG, TIFF · max. 20 MB</p>

                                <div wire:loading wire:target="uploadFile" style="margin-top:.5rem;font-size:.8rem;opacity:.7;">Lädt hoch…</div>

                                @if ($uploadFile)
                                    <div style="margin-top:.75rem;font-size:.85rem;">
                                        Gewählt: <strong>{{ method_exists($uploadFile,'getClientOriginalName') ? $uploadFile->getClientOriginalName() : 'Datei' }}</strong>
                                    </div>
                                    <div style="margin-top:.5rem;">
                                        <x-filament::button wire:click="uploadReceipt" wire:loading.attr="disabled" wire:target="uploadReceipt" icon="heroicon-o-arrow-up-tray" color="primary">
                                            Hochladen, OCR & zuordnen
                                        </x-filament::button>
                                        <span wire:loading wire:target="uploadReceipt" style="margin-left:.5rem;font-size:.8rem;opacity:.7;">OCR läuft…</span>
                                    </div>
                                @endif
                                @error('uploadFile') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @else
                <x-filament::section>
                    <p style="text-align:center;opacity:.6;padding:2rem;">Bitte links einen Umsatz auswählen.</p>
                </x-filament::section>
            @endif
        </div>

        {{-- RECHTS: Belegvorschau --}}
        <x-filament::section style="padding:0;overflow:hidden;">
            <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">Belegvorschau</div>
            <div style="padding:.5rem;">
                @if ($receipt && $receipt->preview_url)
                    @if ($receipt->is_pdf)
                        <iframe src="{{ $receipt->preview_url }}" style="height:72vh;width:100%;border:0;border-radius:.5rem;"></iframe>
                    @else
                        <img src="{{ $receipt->preview_url }}" alt="Beleg" style="max-height:72vh;width:100%;object-fit:contain;border-radius:.5rem;">
                    @endif
                @else
                    <div style="height:60vh;display:flex;align-items:center;justify-content:center;text-align:center;opacity:.5;font-size:.9rem;">
                        Kein Beleg zur Vorschau ausgewählt
                    </div>
                @endif
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
