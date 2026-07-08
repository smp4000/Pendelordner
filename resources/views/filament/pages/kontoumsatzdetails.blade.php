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

            {{-- LINKS: Navigation + Details + Tabs. Eigenes Scroll-Fenster auf
                 Bildschirmhöhe: der (lange) Aufteilungs-Editor scrollt hier
                 intern, unabhängig vom PDF rechts – so sieht man beide zugleich. --}}
            <div class="beleg-scroll left-pane" style="display:flex;flex-direction:column;gap:1rem;position:sticky;top:1rem;align-self:start;max-height:calc(100vh - 2rem);overflow-y:auto;padding-right:.3rem;">

                {{-- Kontokopf + Navigation --}}
                <x-filament::section style="padding:.6rem .8rem;">
                    <div style="font-size:.85rem;opacity:.75;">
                        {{ $tx->bankAccount?->label }}
                        @if ($tx->bankAccount?->iban)<br>{{ $tx->bankAccount->iban }}@endif
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:.5rem;">
                        <button wire:click="goTo('first')" style="{{ $navBtn }}" title="Erster">&#124;&#9664;</button>
                        <button wire:click="goTo('prev')" style="{{ $navBtn }}" title="Zurück">&#9664;</button>
                        <span style="font-size:.85rem;min-width:10rem;text-align:center;">
                            @if ($this->total === 0)
                                <span style="color:#059669;font-weight:600;">Alle Umsätze geprüft ✓</span>
                            @elseif ($this->position === 0)
                                <span style="color:#059669;">geprüft ✓</span> · noch <strong>{{ $this->total }}</strong> offen
                            @else
                                Kontosatz <strong>{{ $this->position }}</strong> von {{ $this->total }}
                            @endif
                        </span>
                        <button wire:click="goTo('next')" style="{{ $navBtn }}" title="Weiter">&#9654;</button>
                        <button wire:click="goTo('last')" style="{{ $navBtn }}" title="Letzter">&#9654;&#124;</button>
                    </div>
                </x-filament::section>

                {{-- Umsatzdetails – wire:key pro Umsatz: erzwingt frische Felder beim Blättern --}}
                <x-filament::section wire:key="detail-{{ $tx->id }}">
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
                            <div style="display:flex;gap:.35rem;align-items:flex-start;">
                                <div style="flex:1;">
                                    @if ($this->currentCategory && ! $editingCategory)
                                        {{-- Gewählte Kategorie als Badge, mit „ändern" zum erneuten Suchen --}}
                                        <x-filament::input.wrapper>
                                            <div style="padding:.4rem .6rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;">
                                                <span style="font-weight:500;">{{ $this->currentCategory->name }}</span>
                                                <button type="button" wire:click="editCategory"
                                                    style="color:#10b981;background:none;border:none;cursor:pointer;font-size:.78rem;white-space:nowrap;">ändern</button>
                                            </div>
                                        </x-filament::input.wrapper>
                                    @else
                                        {{-- Durchsuchbar: Kategoriename ODER eDTAS-Konto (Nummer/Bezeichnung) --}}
                                        <x-filament::input.wrapper>
                                            <x-filament::input type="text" wire:model.live.debounce.250ms="categorySearch"
                                                placeholder="Suchen: Kategorie oder eDTAS-Konto (Nr. oder Text)…" />
                                        </x-filament::input.wrapper>
                                        @php $catRes = $this->categoryResults; $skrRes = $this->edtasResults; @endphp
                                        <div style="margin-top:.25rem;border:1px solid rgba(120,120,120,.2);border-radius:.4rem;max-height:260px;overflow-y:auto;">
                                            @foreach ($catRes as $cat)
                                                <div wire:click="setCategory({{ $cat->id }})"
                                                    style="padding:.35rem .6rem;cursor:pointer;font-size:.85rem;border-bottom:1px solid rgba(120,120,120,.1);display:flex;justify-content:space-between;gap:.5rem;{{ $cat->id === $assignCategoryId ? 'background:rgba(16,185,129,.12);' : '' }}">
                                                    <span>{{ $cat->name }}</span>
                                                    @if ($cat->edtas_account)
                                                        <span style="opacity:.55;white-space:nowrap;">eDTAS {{ $cat->edtas_account }}</span>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @if ($skrRes->isNotEmpty())
                                                <div style="padding:.3rem .6rem;font-size:.7rem;opacity:.6;background:rgba(99,102,241,.07);border-bottom:1px solid rgba(120,120,120,.1);">
                                                    eDTAS-Konto übernehmen (legt Kategorie an)
                                                </div>
                                                @foreach ($skrRes as $la)
                                                    <div wire:click="setCategoryFromEdtas({{ $la->id }})"
                                                        style="padding:.35rem .6rem;cursor:pointer;font-size:.85rem;border-bottom:1px solid rgba(120,120,120,.1);display:flex;justify-content:space-between;gap:.5rem;">
                                                        <span>{{ $la->name }}</span>
                                                        <span style="opacity:.55;white-space:nowrap;color:#4f46e5;">eDTAS {{ $la->number }}</span>
                                                    </div>
                                                @endforeach
                                            @endif

                                            @if ($catRes->isEmpty() && $skrRes->isEmpty())
                                                <div style="padding:.4rem .6rem;font-size:.82rem;opacity:.6;">Nichts gefunden.</div>
                                            @endif
                                        </div>
                                    @endif
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

                    {{-- eDTAS-Konto der gewählten Kategorie (für die Steuerberater-Auswertung) --}}
                    @php $edtas = $this->categoryLedger; @endphp
                    @if ($edtas)
                        <div style="margin-top:.6rem;padding:.4rem .6rem;border:1px solid rgba(99,102,241,.35);border-radius:.45rem;background:rgba(99,102,241,.06);">
                            <div style="font-size:.72rem;opacity:.6;margin-bottom:.2rem;">eDTAS-Konto (aus Kategorie)</div>
                            <div style="display:flex;flex-wrap:wrap;gap:.4rem;font-size:.82rem;">
                                <span style="padding:.15rem .5rem;border-radius:.3rem;background:rgba(99,102,241,.15);color:#4f46e5;font-weight:600;">
                                    eDTAS · {{ $edtas['number'] }}@if ($edtas['name']) – {{ $edtas['name'] }}@endif
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Operatives Sachkonto (edtas/gastro/kfz) mit Suche --}}
                    <div style="margin-top:.6rem;">
                        <label style="display:block;font-size:.78rem;opacity:.6;margin-bottom:.15rem;">Konto (Sachkonto · edtas)</label>
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
                        <x-filament::button wire:click="markReviewed"
                            icon="heroicon-o-shield-check" :color="$tx->reviewed ? 'success' : 'warning'" size="sm"
                            :title="$tx->reviewed ? 'Prüfung zurücknehmen – Status wieder offen' : 'Als geprüft markieren'">
                            {{ $tx->reviewed ? 'Geprüft ✓ – zurücknehmen' : 'Als geprüft markieren' }}
                        </x-filament::button>
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
                        <x-filament::button wire:click="toggleSplitOpen" icon="heroicon-o-clock"
                            :color="$tx->split_open ? 'warning' : 'gray'" size="sm"
                            title="Umsatz als geprüft/bezahlt in den Bericht nehmen, Aufteilung später ergänzen">
                            {{ $tx->split_open ? 'Aufteilung offen ✓' : 'Aufteilung offen merken' }}
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
                                        <option value="amount">Betrag</option>
                                        <option value="any">Beliebig</option>
                                    </x-filament::input.select>
                                </div>
                            </div>

                            {{-- Optionales zweites Kriterium (UND) – nötig bei mehreren Verträgen derselben Gesellschaft --}}
                            <div style="display:grid;grid-template-columns:2fr 1fr;gap:.5rem;margin-top:.5rem;">
                                <div>
                                    <label style="display:block;font-size:.75rem;opacity:.7;margin-bottom:.15rem;">und zusätzlich (optional)</label>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" wire:model="rulePattern2" placeholder="z. B. Vertragsnummer AL-9876085614" />
                                    </x-filament::input.wrapper>
                                </div>
                                <div>
                                    <label style="display:block;font-size:.75rem;opacity:.7;margin-bottom:.15rem;">Feld</label>
                                    <x-filament::input.select wire:model="rulePatternType2">
                                        <option value="purpose">Verwendungszweck</option>
                                        <option value="counterparty">Empfänger</option>
                                        <option value="iban">IBAN</option>
                                        <option value="amount">Betrag</option>
                                        <option value="any">Beliebig</option>
                                    </x-filament::input.select>
                                </div>
                            </div>
                            <div style="font-size:.72rem;opacity:.6;margin-top:.25rem;">
                                Beide Kriterien müssen zutreffen (UND). Text: „enthält"-Suche ohne Groß-/Kleinschreibung; Betrag: exakter Wert (Vorzeichen egal).
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
                            <label style="display:flex;align-items:center;gap:.4rem;margin-top:.5rem;font-size:.8rem;cursor:pointer;">
                                <x-filament::input.checkbox wire:model="noteOpen" />
                                ⚠ Erfordert Reaktion – als offenen Hinweis im Dashboard merken (bis erledigt)
                            </label>
                            <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                                <x-filament::button wire:click="saveNote" icon="heroicon-o-check" color="warning" size="sm">Mitteilung speichern</x-filament::button>
                                <x-filament::button wire:click="toggleNote" color="gray" size="sm">Schließen</x-filament::button>
                            </div>
                        </div>
                    @endif

                    {{-- Aufklappbarer Editor: Betrag auf Sachkonten aufteilen (G&V) --}}
                    @if ($showSplit)
                        <div style="margin-top:.8rem;padding:.7rem;border:1px solid rgba(14,165,233,.35);border-radius:.5rem;background:rgba(14,165,233,.06);">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.2rem;">
                                <div style="font-weight:600;font-size:.85rem;">Betrag auf Sachkonten aufteilen</div>
                                {{-- Netto/Brutto-Schalter --}}
                                <div style="display:flex;align-items:center;gap:.3rem;font-size:.78rem;">
                                    <span style="opacity:.6;">Beträge:</span>
                                    <button type="button" wire:click="setSplitMode('brutto')"
                                        style="padding:.2rem .6rem;border-radius:.35rem;border:1px solid rgba(120,120,120,.3);cursor:pointer;{{ $splitMode==='brutto' ? 'background:#0ea5e9;color:#fff;border-color:#0ea5e9;' : 'background:transparent;' }}">Brutto</button>
                                    <button type="button" wire:click="setSplitMode('netto')"
                                        style="padding:.2rem .6rem;border-radius:.35rem;border:1px solid rgba(120,120,120,.3);cursor:pointer;{{ $splitMode==='netto' ? 'background:#0ea5e9;color:#fff;border-color:#0ea5e9;' : 'background:transparent;' }}">Netto + USt</button>
                                </div>
                            </div>
                            <p style="font-size:.78rem;opacity:.7;margin:0 0 .5rem;">
                                Kategorie und Kostenstelle werden aus der Zuordnung oben übernommen.
                                Im Modus „Netto + USt" wird je Position die Umsatzsteuer aufgeschlagen.
                                <strong>Tipp:</strong> Im Betragsfeld mehrere Positionen mit <strong>+</strong> eingeben
                                (z. B. <code>50+411,32+311,70</code>) – wird automatisch addiert. Änderungen werden automatisch gespeichert.
                            </p>

                            {{-- Aufteilungsvorlagen: fertige Konto-Sätze laden / aktuelle als Vorlage speichern --}}
                            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.4rem;margin-bottom:.6rem;font-size:.8rem;">
                                @if ($this->splitTemplates->isNotEmpty())
                                    <span style="opacity:.6;">Vorlage:</span>
                                    <x-filament::input.select wire:change="applyTemplate($event.target.value)" style="max-width:16rem;">
                                        <option value="">– Vorlage laden –</option>
                                        @foreach ($this->splitTemplates as $tpl)
                                            <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                @endif
                                <span style="opacity:.4;">|</span>
                                <x-filament::input.wrapper style="max-width:12rem;">
                                    <x-filament::input type="text" wire:model="newTemplateName" placeholder="Als Vorlage speichern…" />
                                </x-filament::input.wrapper>
                                <x-filament::button wire:click="saveSplitAsTemplate" icon="heroicon-o-bookmark" color="gray" size="sm">Speichern</x-filament::button>
                                <span style="opacity:.4;">|</span>
                                {{-- USt-Aufteilung direkt aus dem Beleg (gemischte 19/7 %-Rechnungen, z. B. SB-Union) --}}
                                <x-filament::button wire:click="fillSplitFromReceiptTax" icon="heroicon-o-calculator" color="info" size="sm">
                                    USt-Aufteilung aus Beleg
                                </x-filament::button>
                                {{-- Je Beleg eigene Positionen (mit Rechnungsnummer) statt aggregiert --}}
                                <x-filament::button wire:click="fillSplitPerReceipt" icon="heroicon-o-document-duplicate" color="info" size="sm">
                                    USt-Aufteilung je Beleg
                                </x-filament::button>
                            </div>

                            <div style="display:grid;grid-template-columns:2fr .8fr 1fr auto;gap:.4rem;font-size:.72rem;opacity:.65;margin-bottom:.2rem;padding:0 .1rem;">
                                <span>Sachkonto (Kontenrahmen)</span>
                                <span>USt %</span>
                                <span>Betrag € ({{ $splitMode==='netto' ? 'netto' : 'brutto' }})</span>
                                <span></span>
                            </div>

                            @foreach ($splits as $i => $row)
                                <div wire:key="split-{{ $i }}" style="display:grid;grid-template-columns:2fr .8fr 1fr auto;gap:.4rem;align-items:start;margin-bottom:.4rem;">
                                    {{-- Sachkonto durchsuchbar --}}
                                    <div>
                                        @if (! empty($row['booking_text']))
                                            <div style="font-size:.7rem;color:#2563eb;margin-bottom:.1rem;" title="Rechnungsnummer dieser Position (erscheint im Bericht)">
                                                📄 Rechnung {{ $row['booking_text'] }}
                                            </div>
                                        @endif
                                        @if (! empty($row['ledger_account_id']))
                                            <div style="display:flex;align-items:center;gap:.4rem;font-size:.82rem;padding:.3rem 0;">
                                                <span style="padding:.15rem .5rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;">{{ $row['ledger_label'] }}</span>
                                                <button type="button" wire:click="clearSplitLedger({{ $i }})" style="color:#dc2626;background:none;border:none;cursor:pointer;font-size:.78rem;">ändern</button>
                                            </div>
                                        @else
                                            <x-filament::input.wrapper>
                                                <x-filament::input type="text" wire:model.live.debounce.350ms="splits.{{ $i }}.ledger_search" placeholder="Konto suchen (Nummer/Bezeichnung)…" />
                                            </x-filament::input.wrapper>
                                            @php $res = $this->splitLedgerResults($row['ledger_search'] ?? ''); @endphp
                                            @if ($res->isNotEmpty())
                                                <div style="margin-top:.2rem;border:1px solid rgba(120,120,120,.2);border-radius:.4rem;max-height:160px;overflow-y:auto;background:var(--fi-color-white,#fff);">
                                                    @foreach ($res as $la)
                                                        <div wire:click="setSplitLedger({{ $i }}, {{ $la->id }})"
                                                            style="padding:.3rem .55rem;cursor:pointer;font-size:.8rem;border-bottom:1px solid rgba(120,120,120,.1);">
                                                            <strong>{{ $la->number }}</strong> – {{ \Illuminate\Support\Str::limit($la->name, 40) }}
                                                            <span style="opacity:.5;">· {{ $la->chart }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                    <x-filament::input.select wire:model.live="splits.{{ $i }}.tax_rate">
                                        <option value="19">19</option>
                                        <option value="7">7</option>
                                        <option value="0">0</option>
                                    </x-filament::input.select>
                                    <div>
                                        <x-filament::input.wrapper>
                                            <x-filament::input type="text" wire:model.live.debounce.500ms="splits.{{ $i }}.amount" placeholder="z. B. 50+411,32+311,70" />
                                        </x-filament::input.wrapper>
                                        @if (str_contains((string) ($row['amount'] ?? ''), '+'))
                                            <div style="font-size:.72rem;color:#059669;margin-top:.15rem;padding-left:.2rem;">= {{ $this->splitRowSum($i) }} €</div>
                                        @endif
                                    </div>
                                    <button type="button" wire:click="removeSplit({{ $i }})" title="Position entfernen"
                                        style="color:#dc2626;background:none;border:none;cursor:pointer;padding:.3rem;">✕</button>
                                </div>
                            @endforeach

                            @php $rest = $this->splitRemaining; @endphp
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.4rem;font-size:.82rem;">
                                <span>Aufgeteilt (brutto): <strong>{{ number_format($this->splitTotal, 2, ',', '.') }} €</strong>
                                    von {{ number_format(abs((float) $tx->amount), 2, ',', '.') }} €</span>
                                <span style="display:flex;align-items:center;gap:.75rem;">
                                    {{-- Schnellsummen je USt-Satz (Netto), USt+Brutto im Tooltip --}}
                                    @foreach ($this->splitTaxSummary as $satz => $s)
                                        <span title="Netto {{ number_format($s['net'], 2, ',', '.') }} € · USt {{ number_format($s['tax'], 2, ',', '.') }} € · Brutto {{ number_format($s['gross'], 2, ',', '.') }} €"
                                            style="white-space:nowrap;opacity:.85;">
                                            <span style="opacity:.65;">{{ $satz }} %:</span>
                                            <strong>{{ number_format($s['net'], 2, ',', '.') }} €</strong>
                                        </span>
                                    @endforeach
                                    <span style="color:{{ abs($rest) < 0.005 ? '#059669' : '#d97706' }};">
                                        Rest: {{ number_format($rest, 2, ',', '.') }} €
                                    </span>
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
                <x-filament::section style="padding:0;overflow:hidden;" wire:key="tabs-{{ $tx->id }}">
                    <div style="display:flex;gap:.25rem;border-bottom:1px solid rgba(120,120,120,.2);padding:0 .5rem;flex-wrap:wrap;">
                        <button wire:click="setTab('assigned')" style="{{ $tabBtn($activeTab==='assigned') }}">Zugeordnete Belege ({{ $tx->receipts->count() }})</button>
                        <button wire:click="setTab('suggestions')" style="{{ $tabBtn($activeTab==='suggestions') }}">Vorschläge ({{ $this->suggestions->count() }})</button>
                        <button wire:click="setTab('search')" style="{{ $tabBtn($activeTab==='search') }}">Manuelle Belegsuche</button>
                        <button wire:click="setTab('upload')" style="{{ $tabBtn($activeTab==='upload') }}">Beleg hochladen</button>
                    </div>

                    <div style="padding:.5rem;">
                        {{-- Sammel-/Avis-Vorschlag: mehrere Rechnungen ergeben zusammen den Umsatz --}}
                        @php $advice = $this->adviceSuggestion; @endphp
                        @if ($advice)
                            <div style="margin-bottom:.6rem;padding:.7rem .8rem;border:1px solid rgba(16,185,129,.4);border-radius:.5rem;background:rgba(16,185,129,.08);">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
                                    <div style="font-size:.85rem;">
                                        <strong>📄 Sammelzahlung erkannt</strong> – verweist auf
                                        <strong>{{ $advice['invoices']->count() }} Rechnungen</strong>
                                        (Summe {{ number_format($advice['sum'], 2, ',', '.') }} €).
                                        <div style="opacity:.75;margin-top:.2rem;">
                                            {{ $advice['invoices']->map(fn ($r) => ($r->invoice_number ?: ('#' . $r->id)) . ' · ' . number_format((float) $r->gross_amount, 2, ',', '.') . ' €')->join('  |  ') }}
                                        </div>
                                    </div>
                                    <x-filament::button wire:click="attachAdviceInvoices" icon="heroicon-o-check" color="success" size="sm">
                                        {{ $advice['invoices']->count() }} Rechnungen zuordnen
                                    </x-filament::button>
                                </div>
                            </div>
                        @endif

                        {{-- TAB: Zugeordnete Belege (per Drag & Drop sortierbar) --}}
                        @if ($activeTab === 'assigned')
                            @if ($tx->receipts->isEmpty())
                                <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Noch kein Beleg zugeordnet.</p>
                            @else
                                {{-- Summe der Belege passt nicht zum Umsatz: Beträge aus dem Avis
                                     (mit Vorzeichen) übernehmen – korrigiert Sammelzahlungen. --}}
                                @if (abs($tx->difference) > 0.01 && $tx->receipts->count() >= 2)
                                    <div style="margin-bottom:.5rem;padding:.55rem .7rem;border:1px solid rgba(217,119,6,.45);border-radius:.5rem;background:rgba(217,119,6,.08);display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;">
                                        <span style="font-size:.82rem;">
                                            ⚠️ Belegsummen passen nicht zum Umsatz (Differenz
                                            <strong>{{ number_format($tx->difference, 2, ',', '.') }} €</strong>).
                                        </span>
                                        <x-filament::button wire:click="syncAmountsFromAdvice" icon="heroicon-o-arrow-path" color="warning" size="sm">
                                            Beträge aus Avis übernehmen
                                        </x-filament::button>
                                    </div>
                                @endif
                                <div wire:key="assigned-list-{{ $tx->id }}" x-data="receiptSorter()" x-init="init()" x-ref="list">
                                    @foreach ($tx->receipts as $r)
                                        <div wire:key="assigned-{{ $r->id }}" data-id="{{ $r->id }}" wire:click="selectReceipt({{ $r->id }})"
                                            style="{{ $rowBase }}align-items:center;{{ $r->id === $this->selectedReceiptId ? 'background:rgba(16,185,129,.12);' : '' }}">
                                            <span style="display:flex;align-items:center;gap:.5rem;">
                                                {{-- Greifer zum Ziehen (bestimmt auch die Reihenfolge im Bericht) --}}
                                                <span class="receipt-grip" wire:click.stop title="Zum Sortieren ziehen"
                                                    style="cursor:grab;opacity:.45;font-size:1.05rem;line-height:1;user-select:none;touch-action:none;">⠿</span>
                                                <span>{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}
                                                    <span style="opacity:.6;">· {{ $r->supplier?->name }}</span></span>
                                            </span>
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
                                    @endforeach
                                </div>
                            @endif

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
                                <input type="file" x-ref="fileInput" wire:model="uploadFiles" multiple style="display:none" id="belegUpload"
                                    accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,application/pdf,image/*">
                                <div style="font-size:2rem;color:#10b981;">＋</div>
                                <p style="margin:.5rem 0;font-weight:600;">Belege hier ablegen oder</p>
                                <label for="belegUpload" style="display:inline-block;padding:.45rem .9rem;background:#10b981;color:#fff;border-radius:.4rem;cursor:pointer;">Dateien auswählen</label>
                                <p style="margin-top:.5rem;font-size:.75rem;opacity:.6;">Mehrere möglich (bis 100 je Vorgang) · PDF, JPG, PNG, TIFF · max. 20 MB je Datei</p>

                                <div wire:loading wire:target="uploadFiles" style="margin-top:.5rem;font-size:.8rem;opacity:.7;">Lädt hoch…</div>

                                @if (! empty($uploadFiles))
                                    <div style="margin-top:.75rem;font-size:.85rem;">
                                        {{ count($uploadFiles) }} Datei(en) gewählt:
                                        <strong>{{ collect($uploadFiles)->map(fn ($f) => method_exists($f, 'getClientOriginalName') ? $f->getClientOriginalName() : 'Datei')->implode(', ') }}</strong>
                                    </div>
                                    <div style="margin-top:.5rem;">
                                        <x-filament::button wire:click="uploadReceipt" wire:loading.attr="disabled" wire:target="uploadReceipt" icon="heroicon-o-arrow-up-tray" color="primary">
                                            Hochladen, OCR & zuordnen
                                        </x-filament::button>
                                        <span wire:loading wire:target="uploadReceipt" style="margin-left:.5rem;font-size:.8rem;opacity:.7;">OCR läuft…</span>
                                    </div>
                                @endif
                                @error('uploadFiles') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
                                @error('uploadFiles.*') <p style="color:#dc2626;margin-top:.5rem;font-size:.8rem;">{{ $message }}</p> @enderror
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            </div>

            {{-- RECHTS: Belegvorschau – klebt am oberen Rand und bleibt auf eine
                 Bildschirmhöhe begrenzt, damit ein langes (mehrseitiges) PDF die
                 Seite nicht verlängert und die Eingabemaske links stehen bleibt. --}}
            <div class="beleg-scroll" style="position:sticky;top:1rem;align-self:start;max-height:calc(100vh - 2rem);overflow:auto;background:var(--fi-color-white,#fff);border:1px solid rgba(120,120,120,.2);border-radius:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.1);">
                @if ($receipt && $receipt->preview_url)
                    @php $btn = 'display:inline-flex;align-items:center;justify-content:center;width:1.9rem;height:1.9rem;border-radius:.4rem;border:1px solid rgba(120,120,120,.3);background:transparent;cursor:pointer;font-size:1rem;line-height:1;text-decoration:none;color:inherit;'; @endphp
                    <div wire:key="preview-{{ $receipt->id }}"
                        x-data="receiptViewer(@js($receipt->preview_url), {{ $receipt->is_pdf ? 'true' : 'false' }})" x-init="load()">

                        {{-- Kopfleiste: Titel + Zoom/Drucken/Neuer Tab (bleibt beim
                             Scrollen des PDF oben angeheftet). --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.4rem .6rem;border-bottom:1px solid rgba(120,120,120,.2);position:sticky;top:0;background:var(--fi-color-white,#fff);z-index:3;">
                            <span style="font-weight:600;">Belegvorschau</span>
                            <span style="display:flex;align-items:center;gap:.3rem;">
                                <button type="button" @click="zoomOut()" title="Verkleinern" style="{{ $btn }}">−</button>
                                <span x-text="Math.round(zoom*100)+' %'" style="min-width:3rem;text-align:center;font-size:.78rem;opacity:.75;"></span>
                                <button type="button" @click="zoomIn()" title="Vergrößern" style="{{ $btn }}">+</button>
                                <button type="button" @click="reset()" title="Originalgröße" style="{{ $btn }}">⟲</button>
                                <button type="button" @click="lens = !lens" title="Lupe (beim Drüberfahren vergrößern)"
                                    :style="'{{ $btn }}' + (lens ? 'background:#0ea5e9;color:#fff;border-color:#0ea5e9;' : '')">🔍</button>
                                <button type="button" @click="printReceipt()" title="Drucken" style="{{ $btn }}">🖨</button>
                                <a :href="url" target="_blank" title="In neuem Tab öffnen" style="{{ $btn }}">↗</a>
                            </span>
                        </div>

                        <div style="padding:.5rem;position:relative;">
                            {{-- Status-Stempel über der Vorschau: bezahlt / gebucht (wie im Bericht). --}}
                            @if ($tx->fully_paid || $tx->reviewed)
                                <div style="position:absolute;top:1rem;right:1.25rem;z-index:20;display:flex;flex-direction:column;gap:.5rem;align-items:flex-end;pointer-events:none;">
                                    @if ($tx->fully_paid)
                                        <span style="transform:rotate(-8deg);border:2.5px solid #d97706;color:#d97706;background:rgba(255,255,255,.6);font-weight:800;font-size:.95rem;letter-spacing:1.5px;text-transform:uppercase;padding:.15rem .65rem;border-radius:7px;box-shadow:0 1px 2px rgba(0,0,0,.12);">✓ Bezahlt</span>
                                    @endif
                                    @if ($tx->reviewed)
                                        <span style="transform:rotate(-8deg);border:2.5px solid #0d9488;color:#0d9488;background:rgba(255,255,255,.6);font-weight:800;font-size:.95rem;letter-spacing:1.5px;text-transform:uppercase;padding:.15rem .65rem;border-radius:7px;box-shadow:0 1px 2px rgba(0,0,0,.12);">✓ Gebucht</span>
                                    @endif
                                </div>
                            @endif

                            @if ($receipt->is_pdf)
                                {{-- Inline-PDF-Rendering (PDF.js) – volle Länge (alle Seiten
                                     untereinander). overflow-x für breite Belege beim Zoomen. --}}
                                <div class="beleg-scroll" style="overflow-x:auto;background:#fff;border:1px solid rgba(120,120,120,.2);border-radius:.5rem;"
                                    :style="lens ? 'cursor:none;' : ''"
                                    @mousemove="magnify($event)" @mouseleave="lensVisible=false">
                                    {{-- wire:ignore: die per PDF.js erzeugten Canvas-Elemente sollen
                                         bei Livewire-Updates (z. B. Kategorie/Konto ändern) erhalten
                                         bleiben und nicht weggemorpht werden. --}}
                                    <div wire:ignore x-ref="pages" style="padding:10px;text-align:center;"></div>
                                    <template x-if="error">
                                        <div style="color:#374151;padding:1rem;text-align:center;">
                                            Inline-Vorschau nicht möglich.
                                            <a :href="url" target="_blank" style="color:#9ae6b4;text-decoration:underline;">Im neuen Tab öffnen</a>
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

                            {{-- Lupe: folgt dem Cursor und zeigt den Bereich vergrößert --}}
                            <canvas x-ref="lens" x-show="lens && lensVisible" width="240" height="180"
                                style="position:fixed;pointer-events:none;z-index:9999;width:240px;height:180px;border:2px solid #0ea5e9;border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.3);"></canvas>
                        </div>
                    </div>
                @else
                    <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">Belegvorschau</div>
                    <div style="min-height:40vh;display:flex;align-items:center;justify-content:center;text-align:center;opacity:.5;font-size:.9rem;">
                        Kein Beleg zur Vorschau ausgewählt
                    </div>
                @endif
            </div>

        </div>
    @endif

    {{-- PDF.js Bibliothek einmalig laden (auch bei SPA-Navigation) --}}
    @assets
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.6/Sortable.min.js"></script>
    @endassets

    {{-- Immer sichtbarer Scrollbalken für die Belegvorschau (statt Overlay,
         das unter Windows/Chrome erst beim Scrollen auftaucht). --}}
    <style>
        .beleg-scroll { scrollbar-width: thin; scrollbar-color: #9ca3af transparent; }
        .beleg-scroll::-webkit-scrollbar { width: 14px; height: 14px; }
        .beleg-scroll::-webkit-scrollbar-thumb {
            background: #9ca3af; border-radius: 8px; border: 3px solid #fff;
        }
        .beleg-scroll::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .beleg-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 8px; }
        /* Linke Spalte ist ein Flex-Scroll-Fenster: die Abschnitte dürfen NICHT
           schrumpfen, sonst wird die (lange) Belegliste zusammengedrückt und
           abgeschnitten statt scrollbar. flex-shrink:0 => sie laufen über und
           die Spalte scrollt. */
        .left-pane > * { flex-shrink: 0; }
    </style>

    {{-- Alpine-Komponente für die Inline-PDF-Vorschau registrieren --}}
    @script
        <script>
            Alpine.data('receiptViewer', (url, isPdf) => {
                // WICHTIG: Das PDF-Dokument NICHT im reaktiven Alpine-Objekt
                // ablegen. Alpine würde es in einen Proxy packen; pdf.js greift
                // intern auf private Felder (#…) zu, was durch den Proxy bricht
                // ("can't access private field"). Daher als nicht-reaktive
                // Closure-Variable halten.
                let pdfDoc = null;
                let renderRun = 0;

                return {
                    url: url,
                    isPdf: isPdf,
                    error: false,
                    zoom: 1,          // 1 = 100 %
                    baseScale: 1.4,   // Grundschärfe der PDF-Darstellung
                    loadStarted: false,
                    lens: false,        // Lupe an/aus
                    lensVisible: false, // Lupe gerade sichtbar (Cursor über Beleg)
                    lensZoom: 2.6,      // Vergrößerungsfaktor der Lupe
                    // Alpine ruft init() automatisch auf; zusätzlich x-init="load()".
                    // load() ist idempotent (loadStarted), läuft also genau einmal.
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
                        // Nur der jeweils neueste Aufruf darf zeichnen (z. B. bei schnellem Zoomen).
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
                            // Jede Seite als Block mittig; bei 100 % auf Breite
                            // einpassen, beim Hineinzoomen die natürliche (größere)
                            // Breite zulassen, damit der Container horizontal UND
                            // vertikal scrollbar wird (breite Kraftstoffabrechnung).
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
                        // Öffnet den Beleg in einem neuen Fenster und ruft den Druckdialog auf.
                        const w = window.open(this.url, '_blank');
                        if (w) {
                            try { w.addEventListener('load', () => w.print()); } catch (e) { /* Popup evtl. blockiert */ }
                        }
                    },
                    // Lupe: zeichnet den Bereich unter dem Cursor vergrößert in die
                    // Lupen-Fläche. Funktioniert für PDF-Canvas UND Bild.
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

                        // Cursorposition als Anteil im Quellbild -> Quell-Pixel.
                        const fx = (e.clientX - rect.left) / rect.width;
                        const fy = (e.clientY - rect.top) / rect.height;
                        const cx = fx * srcW;
                        const cy = fy * srcH;

                        // Auszuschneidender Bereich (in Quell-Pixeln), abhängig vom Zoom.
                        const lw = lensEl.width, lh = lensEl.height;
                        const regionW = (lw / this.lensZoom) * (srcW / rect.width);
                        const regionH = (lh / this.lensZoom) * (srcH / rect.height);

                        const ctx = lensEl.getContext('2d');
                        ctx.fillStyle = '#fff';
                        ctx.fillRect(0, 0, lw, lh);
                        try {
                            ctx.drawImage(src, cx - regionW / 2, cy - regionH / 2, regionW, regionH, 0, 0, lw, lh);
                        } catch (err) { return; }

                        // Lupe neben dem Cursor positionieren (im Viewport halten).
                        let px = e.clientX + 18, py = e.clientY + 18;
                        if (px + lw > window.innerWidth) { px = e.clientX - lw - 18; }
                        if (py + lh > window.innerHeight) { py = e.clientY - lh - 18; }
                        lensEl.style.left = px + 'px';
                        lensEl.style.top = py + 'px';
                        this.lensVisible = true;
                    },
                };
            });

            // Drag & Drop für die Reihenfolge der zugeordneten Belege.
            Alpine.data('receiptSorter', () => ({
                sortable: null,
                init() {
                    if (! window.Sortable) {
                        return;
                    }
                    this.sortable = window.Sortable.create(this.$refs.list, {
                        handle: '.receipt-grip',
                        animation: 150,
                        ghostClass: 'opacity-50',
                        onEnd: () => {
                            const ids = Array.from(this.$refs.list.querySelectorAll('[data-id]'))
                                .map((el) => parseInt(el.dataset.id, 10));
                            this.$wire.reorderReceipts(ids);
                        },
                    });
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
