<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $tx = $this->selectedTransaction;
        $receipt = $this->selectedReceipt;
        $navBtn = 'padding:.25rem .55rem;border:1px solid rgba(120,120,120,.3);border-radius:.35rem;background:transparent;cursor:pointer;font-size:.9rem;line-height:1;';
        $rowBase = 'display:flex;justify-content:space-between;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);cursor:pointer;';
        $tabBtn = fn ($active) => 'padding:.45rem .8rem;border:0;background:none;cursor:pointer;font-size:.85rem;border-bottom:2px solid '
            . ($active ? '#10b981;font-weight:600;color:#059669;' : 'transparent;opacity:.7;');
    @endphp

    @if (! $tx)
        <x-filament::section>
            <p style="text-align:center;opacity:.6;padding:2rem;">Keine offenen Umsätze 🎉</p>
        </x-filament::section>
    @else
        <div style="display:grid;grid-template-columns:2fr 3fr;gap:1rem;align-items:start;">

            {{-- LINKS: Navigation + Details + Tabs --}}
            <div style="display:flex;flex-direction:column;gap:1rem;">

                {{-- Kontokopf + Navigation --}}
                <x-filament::section style="padding:.6rem .8rem;">
                    <div style="font-size:.85rem;opacity:.75;">
                        {{ $tx->bankAccount?->label }}
                        @if ($tx->bankAccount?->iban)<br>{{ $tx->bankAccount->iban }}@endif
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:.5rem;">
                        <button wire:click="goTo('first')" style="{{ $navBtn }}" title="Erster">&#124;&#9664;</button>
                        <button wire:click="goTo('prev')" style="{{ $navBtn }}" title="Zurück">&#9664;</button>
                        <span style="font-size:.85rem;min-width:10rem;text-align:center;">Kontosatz <strong>{{ $this->position }}</strong> von {{ $this->total }}</span>
                        <button wire:click="goTo('next')" style="{{ $navBtn }}" title="Weiter">&#9654;</button>
                        <button wire:click="goTo('last')" style="{{ $navBtn }}" title="Letzter">&#9654;&#124;</button>
                    </div>
                </x-filament::section>

                {{-- Umsatzdetails --}}
                <x-filament::section>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                        <div>
                            <div style="font-size:1.1rem;font-weight:600;">{{ $tx->counterparty ?: 'Bankumsatz' }}</div>
                            <div style="font-size:.85rem;opacity:.6;">Buchungsdatum {{ $tx->booking_date?->format('d.m.Y') }}</div>
                        </div>
                        <div style="font-size:1.25rem;font-weight:700;white-space:nowrap;color:{{ $tx->amount < 0 ? '#dc2626' : '#059669' }};">{{ $money($tx->amount) }}</div>
                    </div>
                    @if ($tx->clean_purpose !== '')
                        <p style="margin-top:.5rem;font-size:.85rem;" title="{{ $tx->purpose }}">{{ \Illuminate\Support\Str::limit($tx->clean_purpose, 160) }}</p>
                    @endif
                    {{-- Zuordnung (direkt speichern) --}}
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:.7rem;">
                        <div>
                            <label style="display:block;font-size:.78rem;opacity:.6;margin-bottom:.15rem;">Kategorie</label>
                            <div style="display:flex;gap:.35rem;align-items:center;">
                                <div style="flex:1;">
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model.live="assignCategoryId">
                                            <option value="">—</option>
                                            @foreach ($this->categories as $cat)
                                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                            @endforeach
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </div>
                                <button type="button" wire:click="toggleNewCategory" title="Neue Kategorie anlegen"
                                    style="flex:0 0 auto;width:2.25rem;height:2.25rem;border:1px solid #10b981;color:#10b981;background:transparent;border-radius:.4rem;cursor:pointer;font-size:1.1rem;line-height:1;">+</button>
                            </div>
                            @if ($showNewCategory)
                                <div style="display:flex;gap:.35rem;margin-top:.35rem;">
                                    <x-filament::input.wrapper style="flex:1;">
                                        <x-filament::input type="text" wire:model="newCategory" placeholder="Neue Kategorie…"
                                            wire:keydown.enter="createCategory" />
                                    </x-filament::input.wrapper>
                                    <x-filament::button wire:click="createCategory" size="sm" color="success">Anlegen</x-filament::button>
                                </div>
                            @endif
                        </div>
                        <div>
                            <label style="display:block;font-size:.78rem;opacity:.6;margin-bottom:.15rem;">Kostenstelle</label>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model.live="assignCostCenterId">
                                    <option value="">—</option>
                                    @foreach ($this->costCenters as $cc)
                                        <option value="{{ $cc->id }}">{{ $cc->name }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>
                    </div>

                    {{-- Sachkonto (Kontenrahmen) mit Suche --}}
                    <div style="margin-top:.6rem;">
                        <label style="display:block;font-size:.78rem;opacity:.6;margin-bottom:.15rem;">Konto (Sachkonto / Kontenrahmen)</label>
                        @if ($this->currentLedger)
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;">
                                <span style="padding:.15rem .5rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;font-weight:500;">
                                    {{ $this->currentLedger->number }} – {{ $this->currentLedger->name }}
                                </span>
                                <button type="button" wire:click="clearLedger" style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:.8rem;">entfernen</button>
                            </div>
                        @else
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model.live.debounce.350ms="ledgerSearch" placeholder="Konto suchen (Nummer oder Bezeichnung)…" />
                            </x-filament::input.wrapper>
                            @if ($this->ledgerResults->isNotEmpty())
                                <div style="margin-top:.25rem;border:1px solid rgba(120,120,120,.2);border-radius:.4rem;max-height:180px;overflow-y:auto;">
                                    @foreach ($this->ledgerResults as $la)
                                        <div wire:click="setLedger({{ $la->id }})"
                                            style="padding:.35rem .6rem;cursor:pointer;font-size:.82rem;border-bottom:1px solid rgba(120,120,120,.1);">
                                            <strong>{{ $la->number }}</strong> – {{ $la->name }}
                                            <span style="opacity:.5;">· {{ $la->chart }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-top:.6rem;font-size:.85rem;">
                        <div><span style="opacity:.6;">Status:</span> {{ $tx->status->getLabel() }}</div>
                        <div><span style="opacity:.6;">Differenz:</span>
                            <span style="color:{{ abs($tx->difference) < 0.01 ? '#059669' : '#d97706' }};">{{ $money($tx->difference) }}</span></div>
                    </div>
                    <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap;">
                        <x-filament::button wire:click="markReviewed" icon="heroicon-o-shield-check" color="success" size="sm">Als geprüft markieren</x-filament::button>
                        <x-filament::button wire:click="togglePaid" icon="heroicon-o-banknotes" :color="$tx->fully_paid ? 'success' : 'gray'" size="sm">
                            {{ $tx->fully_paid ? 'Vollständig bezahlt ✓' : 'Als bezahlt markieren' }}
                        </x-filament::button>
                        <x-filament::button wire:click="toggleNote" icon="heroicon-o-chat-bubble-left-ellipsis"
                            :color="$tx->accountant_note ? 'warning' : 'gray'" size="sm">
                            {{ $tx->accountant_note ? 'Mitteilung ✓' : 'Mitteilung an Steuerberater' }}
                        </x-filament::button>
                        <x-filament::button wire:click="toggleRuleForm" icon="heroicon-o-bolt" color="gray" size="sm">
                            Regel erstellen
                        </x-filament::button>
                        <x-filament::button wire:click="toggleSplit" icon="heroicon-o-scissors"
                            :color="$tx->accountAssignments->isNotEmpty() ? 'info' : 'gray'" size="sm">
                            {{ $tx->accountAssignments->isNotEmpty() ? 'Aufteilung (' . $tx->accountAssignments->count() . ')' : 'Betrag aufteilen' }}
                        </x-filament::button>
                        <x-filament::button tag="a" href="{{ \App\Filament\Resources\BankTransactions\BankTransactionResource::getUrl('edit', ['record' => $tx]) }}" icon="heroicon-o-pencil-square" color="gray" size="sm">Bearbeiten</x-filament::button>
                    </div>

                    {{-- Aufklappbares Formular: Zuordnungsregel für wiederkehrende Buchungen --}}
                    @if ($showRuleForm)
                        <div style="margin-top:.8rem;padding:.7rem;border:1px solid rgba(16,185,129,.35);border-radius:.5rem;background:rgba(16,185,129,.06);">
                            <div style="font-weight:600;font-size:.85rem;margin-bottom:.5rem;">Regel für wiederkehrende Buchungen</div>
                            <div style="display:grid;grid-template-columns:2fr 1fr;gap:.5rem;">
                                <div>
                                    <label style="display:block;font-size:.75rem;opacity:.7;margin-bottom:.15rem;">Muster (Suchbegriff)</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model="rulePattern" placeholder="z. B. Allianz" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label style="display:block;font-size:.75rem;opacity:.7;margin-bottom:.15rem;">Feld</label>
                                    <x-filament::input.select wire:model="rulePatternType">
                                        <option value="counterparty">Empfänger</option>
                                        <option value="purpose">Verwendungszweck</option>
                                        <option value="iban">IBAN</option>
                                        <option value="any">Beliebig</option>
                                    </x-filament::input.select>
                                </div>
                            </div>
                            <div style="font-size:.78rem;opacity:.75;margin-top:.5rem;">
                                Übernimmt:
                                <strong>{{ $tx->category?->name ?? '—' }}</strong> (Kategorie),
                                <strong>{{ $tx->costCenter?->name ?? '—' }}</strong> (Kostenstelle),
                                <strong>{{ $tx->ledgerAccount?->number ?? '—' }}</strong> (Konto)
                            </div>
                            <label style="display:flex;align-items:center;gap:.4rem;margin-top:.5rem;font-size:.8rem;cursor:pointer;">
                                <x-filament::input.checkbox wire:model="ruleApplyExisting" />
                                Sofort auf vorhandene ungeprüfte Umsätze anwenden
                            </label>
                            <div style="display:flex;gap:.5rem;margin-top:.6rem;">
                                <x-filament::button wire:click="createRule" icon="heroicon-o-check" color="success" size="sm">Regel speichern</x-filament::button>
                                <x-filament::button wire:click="toggleRuleForm" color="gray" size="sm">Schließen</x-filament::button>
                            </div>
                        </div>
                    @endif

                    {{-- Aufklappbares Memo-Feld: Mitteilung an den Steuerberater --}}
                    @if ($showNote)
                        <div style="margin-top:.8rem;padding:.6rem;border:1px solid rgba(217,119,6,.35);border-radius:.5rem;background:rgba(217,119,6,.06);">
                            <label style="display:block;font-size:.78rem;opacity:.7;margin-bottom:.3rem;">
                                Mitteilung an den Steuerberater (erscheint fett unter dem Umsatz im Bericht)
                            </label>
                            <x-filament::input.wrapper>
                                <textarea wire:model="accountantNote" rows="2"
                                    placeholder="z. B. Betrifft privates Fahrzeug"
                                    style="width:100%;border:none;background:transparent;outline:none;resize:vertical;font-size:.85rem;padding:.3rem;"></textarea>
                            </x-filament::input.wrapper>
                            <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                <x-filament::button wire:click="saveNote" icon="heroicon-o-check" color="warning" size="sm">Mitteilung speichern</x-filament::button>
                                <x-filament::button wire:click="toggleNote" color="gray" size="sm">Schließen</x-filament::button>
                            </div>
                        </div>
                    @endif

                    {{-- Aufklappbarer Editor: Betrag auf Kategorien aufteilen (G&V) --}}
                    @if ($showSplit)
                        <div style="margin-top:.8rem;padding:.7rem;border:1px solid rgba(14,165,233,.35);border-radius:.5rem;background:rgba(14,165,233,.06);">
                            <div style="font-weight:600;font-size:.85rem;margin-bottom:.2rem;">Betrag auf Kategorien aufteilen</div>
                            <p style="font-size:.78rem;opacity:.7;margin:0 0 .6rem;">
                                Für die Gewinn- und Verlustrechnung: den Umsatzbetrag auf mehrere Kategorien
                                aufteilen (z. B. Kosten, Lotto neutral, Provision).
                            </p>

                            @foreach ($splits as $i => $row)
                                <div wire:key="split-{{ $i }}" style="display:grid;grid-template-columns:1.6fr 1fr auto;gap:.4rem;align-items:center;margin-bottom:.4rem;">
                                    <x-filament::input.select wire:model="splits.{{ $i }}.category_id">
                                        <option value="">Kategorie wählen…</option>
                                        @foreach ($this->categories as $cat)
                                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model.live.debounce.400ms="splits.{{ $i }}.amount" placeholder="Betrag €" />
                                    </x-filament::input.wrapper>
                                    <button type="button" wire:click="removeSplit({{ $i }})" title="Position entfernen"
                                        style="color:#dc2626;background:none;border:none;cursor:pointer;padding:.3rem;">✕</button>
                                </div>
                            @endforeach

                            @php $rest = $this->splitRemaining; @endphp
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.4rem;font-size:.82rem;">
                                <span>Aufgeteilt: <strong>{{ number_format($this->splitTotal, 2, ',', '.') }} €</strong>
                                    von {{ number_format(abs((float) $tx->amount), 2, ',', '.') }} €</span>
                                <span style="color:{{ abs($rest) < 0.005 ? '#059669' : '#d97706' }};">
                                    Rest: {{ number_format($rest, 2, ',', '.') }} €
                                </span>
                            </div>

                            <div style="display:flex;gap:.5rem;margin-top:.6rem;">
                                <x-filament::button wire:click="addSplit" icon="heroicon-o-plus" color="gray" size="sm">Position</x-filament::button>
                                <x-filament::button wire:click="saveSplits" icon="heroicon-o-check" color="info" size="sm">Aufteilung speichern</x-filament::button>
                                <x-filament::button wire:click="toggleSplit" color="gray" size="sm">Schließen</x-filament::button>
                            </div>
                        </div>
                    @endif
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
                                <div wire:key="assigned-{{ $r->id }}" wire:click="selectReceipt({{ $r->id }})"
                                    style="{{ $rowBase }}align-items:center;{{ $r->id === $this->selectedReceiptId ? 'background:rgba(16,185,129,.12);' : '' }}">
                                    <span>{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}
                                        <span style="opacity:.6;">· {{ $r->supplier?->name }}</span></span>
                                    <span style="display:flex;gap:.5rem;align-items:center;white-space:nowrap;">
                                        <label wire:click.stop title="Im Steuerberater-Bericht anhängen"
                                            style="display:flex;align-items:center;gap:.25rem;font-size:.75rem;opacity:.8;cursor:pointer;">
                                            <input type="checkbox" wire:click.stop="toggleReceiptInReport({{ $r->id }})"
                                                @checked($r->include_in_report)>
                                            im Bericht
                                        </label>
                                        <input type="number" step="0.01" value="{{ $r->pivot->amount }}"
                                            wire:click.stop
                                            wire:change.stop="updateAllocation({{ $r->id }}, $event.target.value)"
                                            style="width:7rem;text-align:right;border:1px solid rgba(120,120,120,.3);border-radius:.3rem;padding:.15rem .4rem;"> €
                                        <button type="button" wire:click.stop="detachReceipt({{ $r->id }})" style="color:#dc2626;background:none;border:none;cursor:pointer;">lösen</button>
                                    </span>
                                </div>
                            @empty
                                <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Noch kein Beleg zugeordnet.</p>
                            @endforelse

                        {{-- TAB: Vorschläge --}}
                        @elseif ($activeTab === 'suggestions')
                            @forelse ($this->suggestions as $s)
                                <div wire:key="suggest-{{ $s['receipt']->id }}"
                                    style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);{{ $s['receipt']->id === $this->selectedReceipt?->id ? 'background:rgba(16,185,129,.12);' : '' }}">
                                    <span wire:click="selectReceipt({{ $s['receipt']->id }})" style="cursor:pointer;flex:1;" title="In der Vorschau anzeigen">
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

                        {{-- TAB: Beleg hochladen --}}
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
            </div>

            {{-- RECHTS: Belegvorschau --}}
            <x-filament::section style="padding:0;overflow:hidden;">
                <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">Belegvorschau</div>
                <div style="padding:.5rem;">
                    @if ($receipt && $receipt->preview_url)
                        @if ($receipt->is_pdf)
                            {{-- Inline-PDF-Rendering (PDF.js) – öffnet kein neues Fenster --}}
                            <div wire:key="pdf-{{ $receipt->id }}" x-data="pdfViewer(@js($receipt->preview_url))" x-init="load()"
                                style="height:80vh;overflow:auto;background:#525659;border-radius:.5rem;">
                                {{-- wire:ignore: die per PDF.js erzeugten Canvas-Elemente sollen
                                     bei Livewire-Updates (z. B. Kategorie/Konto ändern) erhalten
                                     bleiben und nicht weggemorpht werden. --}}
                                <div wire:ignore x-ref="pages" style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:10px;"></div>
                                <template x-if="error">
                                    <div style="color:#fff;padding:1rem;text-align:center;">
                                        Inline-Vorschau nicht möglich.
                                        <a :href="url" target="_blank" style="color:#9ae6b4;text-decoration:underline;">Im neuen Tab öffnen</a>
                                    </div>
                                </template>
                            </div>
                        @else
                            <img src="{{ $receipt->preview_url }}" alt="Beleg" style="max-height:80vh;width:100%;object-fit:contain;border-radius:.5rem;">
                        @endif
                    @else
                        <div style="height:70vh;display:flex;align-items:center;justify-content:center;text-align:center;opacity:.5;font-size:.9rem;">
                            Kein Beleg zur Vorschau ausgewählt
                        </div>
                    @endif
                </div>
            </x-filament::section>

        </div>
    @endif

    {{-- PDF.js Bibliothek einmalig laden (auch bei SPA-Navigation) --}}
    @assets
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    @endassets

    {{-- Alpine-Komponente für die Inline-PDF-Vorschau registrieren --}}
    @script
        <script>
            Alpine.data('pdfViewer', (url) => ({
                url: url,
                error: false,
                async load() {
                    try {
                        let tries = 0;
                        while (!window.pdfjsLib && tries < 160) {
                            await new Promise((r) => setTimeout(r, 50));
                            tries++;
                        }
                        if (!window.pdfjsLib) { this.error = true; return; }

                        window.pdfjsLib.GlobalWorkerOptions.workerSrc =
                            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                        const pdf = await window.pdfjsLib.getDocument(this.url).promise;
                        const container = this.$refs.pages;
                        container.innerHTML = '';
                        for (let i = 1; i <= pdf.numPages; i++) {
                            const page = await pdf.getPage(i);
                            const viewport = page.getViewport({ scale: 1.4 });
                            const canvas = document.createElement('canvas');
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            canvas.style.maxWidth = '100%';
                            canvas.style.background = '#fff';
                            canvas.style.boxShadow = '0 1px 4px rgba(0,0,0,.35)';
                            container.appendChild(canvas);
                            await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                        }
                    } catch (e) {
                        console.error(e);
                        this.error = true;
                    }
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
