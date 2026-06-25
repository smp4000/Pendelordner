<x-filament-panels::page>
    @php
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' €';
        $total = $this->total;
        $pct = fn ($v) => $total > 0 ? round((float) $v / $total * 100) : 0;

        $blocks = [
            ['Kosten je Betrieb', $this->byBusiness],
            ['Kosten je Kostenstelle', $this->byCostCenter],
            ['Kosten je Kategorie', $this->byCategory],
            ['Kosten je Bankkonto', $this->byBankAccount],
            ['Top-Lieferanten', $this->bySupplier],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Steuerung --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="w-64">
                <label class="block text-sm font-medium mb-1">Zeitraum</label>
                <select wire:model.live="period"
                    class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    @foreach ($this->periodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">Gesamtkosten</div>
                <div class="text-2xl font-bold text-danger-600">{{ $money($total) }}</div>
            </div>
        </div>

        {{-- Aufschlüsselungen --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($blocks as [$title, $rows])
                <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                        {{ $title }}
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($rows as $row)
                            <div class="px-4 py-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="truncate">{{ $row->label }}
                                        <span class="text-xs text-gray-400">({{ $row->anzahl }})</span>
                                    </span>
                                    <span class="font-medium whitespace-nowrap">{{ $money($row->total) }}</span>
                                </div>
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ $pct($row->total) }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-sm text-gray-500">Keine Daten im Zeitraum.</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
