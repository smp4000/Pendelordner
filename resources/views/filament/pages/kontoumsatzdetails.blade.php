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
    @endphp

    <div style="display:grid;grid-template-columns:3fr 5fr 4fr;gap:1rem;align-items:start;">

        {{-- LINKS: offene Umsätze --}}
        <x-filament::section style="padding:0;overflow:hidden;">
            <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">
                Offene Umsätze ({{ $this->openTransactions->count() }})
            </div>
            <div style="max-height:70vh;overflow-y:auto;">
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

        {{-- MITTE: Details + Belege + Vorschläge --}}
        <div style="display:flex;flex-direction:column;gap:1rem;">
            @if ($tx)
                <x-filament::section>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;">
                        <div>
                            <div style="font-size:1.1rem;font-weight:600;">{{ $tx->counterparty ?: 'Bankumsatz' }}</div>
                            <div style="font-size:.85rem;opacity:.6;">{{ $tx->booking_date?->format('d.m.Y') }} · {{ $tx->bankAccount?->label }}</div>
                        </div>
                        <div style="font-size:1.25rem;font-weight:700;white-space:nowrap;color:{{ $tx->amount < 0 ? '#dc2626' : '#059669' }};">{{ $money($tx->amount) }}</div>
                    </div>

                    @if ($tx->purpose)
                        <p style="margin-top:.5rem;font-size:.85rem;opacity:.8;">{{ $tx->purpose }}</p>
                    @endif

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.75rem;font-size:.85rem;">
                        <div><span style="opacity:.6;">Kategorie:</span> {{ $tx->category?->name ?? '—' }}</div>
                        <div><span style="opacity:.6;">Kostenstelle:</span> {{ $tx->costCenter?->name ?? '—' }}</div>
                        <div><span style="opacity:.6;">Status:</span> {{ $tx->status->getLabel() }}</div>
                        <div><span style="opacity:.6;">Differenz:</span>
                            <span style="color:{{ abs($tx->difference) < 0.01 ? '#059669' : '#d97706' }};">{{ $money($tx->difference) }}</span></div>
                    </div>

                    <div style="display:flex;gap:.5rem;margin-top:1rem;">
                        <x-filament::button wire:click="markReviewed" icon="heroicon-o-shield-check" color="success" size="sm">
                            Als geprüft markieren
                        </x-filament::button>
                        <x-filament::button tag="a"
                            href="{{ \App\Filament\Resources\BankTransactions\BankTransactionResource::getUrl('edit', ['record' => $tx]) }}"
                            icon="heroicon-o-pencil-square" color="gray" size="sm">
                            Bearbeiten
                        </x-filament::button>
                    </div>
                </x-filament::section>

                {{-- Zugeordnete Belege --}}
                <x-filament::section style="padding:0;overflow:hidden;">
                    <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">
                        Zugeordnete Belege ({{ $tx->receipts->count() }})
                    </div>
                    @forelse ($tx->receipts as $r)
                        <div wire:click="selectReceipt({{ $r->id }})"
                            style="{{ $rowBase }}align-items:center;{{ $r->id === $this->selectedReceiptId ? 'background:rgba(16,185,129,.12);' : '' }}">
                            <span>{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}
                                <span style="opacity:.6;">· {{ $r->supplier?->name }}</span></span>
                            <span style="display:flex;gap:.75rem;align-items:center;white-space:nowrap;">
                                {{ $money($r->pivot->amount) }}
                                <button type="button" wire:click.stop="detachReceipt({{ $r->id }})"
                                    style="color:#dc2626;background:none;border:none;cursor:pointer;">lösen</button>
                            </span>
                        </div>
                    @empty
                        <p style="padding:.75rem;font-size:.85rem;opacity:.6;">Noch kein Beleg zugeordnet.</p>
                    @endforelse
                </x-filament::section>

                {{-- Vorschläge --}}
                @if ($this->suggestions->isNotEmpty())
                    <x-filament::section style="padding:0;overflow:hidden;">
                        <div style="padding:.5rem .75rem;font-weight:600;border-bottom:1px solid rgba(120,120,120,.2);">Vorschläge</div>
                        @foreach ($this->suggestions as $s)
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.5rem .75rem;border-bottom:1px solid rgba(120,120,120,.15);">
                                <span>{{ $s['receipt']->invoice_number ?: ('Beleg #' . $s['receipt']->id) }}
                                    <span style="opacity:.6;">· {{ $money($s['receipt']->gross_amount) }}</span>
                                    <span style="margin-left:.4rem;padding:.05rem .4rem;border-radius:.3rem;background:rgba(16,185,129,.15);color:#059669;font-size:.75rem;">{{ $s['score'] }} %</span>
                                </span>
                                <x-filament::button wire:click="attachReceipt({{ $s['receipt']->id }})" size="sm" color="primary">
                                    Zuordnen
                                </x-filament::button>
                            </div>
                        @endforeach
                    </x-filament::section>
                @endif
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
                        <iframe src="{{ $receipt->preview_url }}" style="height:70vh;width:100%;border:0;border-radius:.5rem;"></iframe>
                    @else
                        <img src="{{ $receipt->preview_url }}" alt="Beleg" style="max-height:70vh;width:100%;object-fit:contain;border-radius:.5rem;">
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
