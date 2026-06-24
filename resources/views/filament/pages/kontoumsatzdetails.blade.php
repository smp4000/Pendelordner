<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $tx = $this->selectedTransaction;
        $receipt = $this->selectedReceipt;
    @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">

        {{-- LINKS: offene Umsätze --}}
        <div class="lg:col-span-3 rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                Offene Umsätze ({{ $this->openTransactions->count() }})
            </div>
            <div class="max-h-[70vh] overflow-y-auto divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($this->openTransactions as $row)
                    <button type="button" wire:click="selectTransaction({{ $row->id }})"
                        @class([
                            'block w-full px-4 py-3 text-left text-sm transition hover:bg-gray-50 dark:hover:bg-white/5',
                            'bg-primary-50 dark:bg-primary-500/10' => $row->id === $this->selectedTransactionId,
                            'border-l-4 border-l-danger-500' => $row->status === \App\Enums\TransactionStatus::Open,
                            'border-l-4 border-l-warning-500' => $row->status === \App\Enums\TransactionStatus::PartiallyAllocated,
                        ])>
                        <div class="flex justify-between">
                            <span class="font-medium truncate">{{ $row->counterparty ?: 'Unbekannt' }}</span>
                            <span class="{{ $row->amount < 0 ? 'text-danger-600' : 'text-success-600' }} whitespace-nowrap">{{ $money($row->amount) }}</span>
                        </div>
                        <div class="mt-1 flex justify-between text-xs text-gray-500">
                            <span>{{ $row->booking_date?->format('d.m.Y') }}</span>
                            <span>{{ $row->receipts->count() }} Beleg(e)</span>
                        </div>
                    </button>
                @empty
                    <p class="px-4 py-6 text-sm text-gray-500">Keine offenen Umsätze 🎉</p>
                @endforelse
            </div>
        </div>

        {{-- MITTE: Details + Belege + Vorschläge --}}
        <div class="lg:col-span-5 space-y-4">
            @if ($tx)
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold">{{ $tx->counterparty ?: 'Bankumsatz' }}</h3>
                            <p class="text-sm text-gray-500">{{ $tx->booking_date?->format('d.m.Y') }} · {{ $tx->bankAccount?->label }}</p>
                        </div>
                        <span class="text-xl font-bold {{ $tx->amount < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $money($tx->amount) }}</span>
                    </div>

                    @if ($tx->purpose)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $tx->purpose }}</p>
                    @endif

                    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                        <div><dt class="text-gray-500">Kategorie</dt><dd>{{ $tx->category?->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Kostenstelle</dt><dd>{{ $tx->costCenter?->name ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">Status</dt><dd>{{ $tx->status->getLabel() }}</dd></div>
                        <div><dt class="text-gray-500">Differenz</dt>
                            <dd class="{{ abs($tx->difference) < 0.01 ? 'text-success-600' : 'text-warning-600' }}">{{ $money($tx->difference) }}</dd></div>
                    </dl>

                    <div class="mt-4 flex gap-2">
                        <x-filament::button wire:click="markReviewed" icon="heroicon-o-shield-check" color="success" size="sm">
                            Als geprüft markieren
                        </x-filament::button>
                        <x-filament::button tag="a" href="{{ \App\Filament\Resources\BankTransactions\BankTransactionResource::getUrl('edit', ['record' => $tx]) }}"
                            icon="heroicon-o-pencil-square" color="gray" size="sm">
                            Bearbeiten
                        </x-filament::button>
                    </div>
                </div>

                {{-- Zugeordnete Belege --}}
                <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                        Zugeordnete Belege ({{ $tx->receipts->count() }})
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($tx->receipts as $r)
                            <div @class([
                                'flex items-center justify-between px-4 py-2 text-sm cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-primary-50 dark:bg-primary-500/10' => $r->id === $this->selectedReceiptId,
                            ]) wire:click="selectReceipt({{ $r->id }})">
                                <div>
                                    <span class="font-medium">{{ $r->invoice_number ?: ('Beleg #' . $r->id) }}</span>
                                    <span class="text-gray-500"> · {{ $r->supplier?->name }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span>{{ $money($r->pivot->amount) }}</span>
                                    <button type="button" wire:click.stop="detachReceipt({{ $r->id }})"
                                        class="text-danger-600 hover:underline">lösen</button>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-4 text-sm text-gray-500">Noch kein Beleg zugeordnet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Vorschläge der Matching-Engine --}}
                @if ($this->suggestions->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                            Vorschläge
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($this->suggestions as $s)
                                <div class="flex items-center justify-between px-4 py-2 text-sm">
                                    <div>
                                        <span class="font-medium">{{ $s['receipt']->invoice_number ?: ('Beleg #' . $s['receipt']->id) }}</span>
                                        <span class="text-gray-500"> · {{ $money($s['receipt']->gross_amount) }}</span>
                                        <span class="ml-2 rounded bg-success-100 px-1.5 py-0.5 text-xs text-success-700">{{ $s['score'] }} %</span>
                                    </div>
                                    <x-filament::button wire:click="attachReceipt({{ $s['receipt']->id }})" size="sm" color="primary">
                                        Zuordnen
                                    </x-filament::button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                    Bitte links einen Umsatz auswählen.
                </div>
            @endif
        </div>

        {{-- RECHTS: Belegvorschau --}}
        <div class="lg:col-span-4 rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                Belegvorschau
            </div>
            <div class="p-2">
                @if ($receipt && $receipt->preview_url)
                    @if ($receipt->is_pdf)
                        <iframe src="{{ $receipt->preview_url }}" class="h-[70vh] w-full rounded-lg border-0"></iframe>
                    @else
                        <img src="{{ $receipt->preview_url }}" alt="Beleg" class="max-h-[70vh] w-full rounded-lg object-contain">
                    @endif
                @else
                    <div class="flex h-[70vh] items-center justify-center text-center text-sm text-gray-400">
                        Kein Beleg zur Vorschau ausgewählt
                        <br>(oder keine Datei hinterlegt).
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-filament-panels::page>
