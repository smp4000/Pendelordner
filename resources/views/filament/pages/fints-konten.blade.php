<x-filament-panels::page>
    <div class="space-y-6">

        {{-- 1. Zugang wählen --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <label class="block text-sm font-medium mb-1">FinTS-Zugang</label>
            <div class="flex flex-wrap items-end gap-3">
                <select wire:model.live="connectionId"
                    class="block w-72 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                    <option value="">– bitte wählen –</option>
                    @foreach ($this->connections as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                <div>
                    <label class="block text-sm font-medium mb-1">PIN</label>
                    <input type="password" wire:model="pin" autocomplete="off"
                        placeholder="PIN eingeben"
                        class="block w-48 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                </div>

                <x-filament::button wire:click="discover" wire:loading.attr="disabled" icon="heroicon-o-magnifying-glass"
                    :disabled="! $connectionId">
                    Konten abrufen
                    <span wire:loading wire:target="discover">…</span>
                </x-filament::button>
            </div>
            <p class="mt-2 text-xs text-gray-400">
                PIN hier eingeben (wird nur für den Abruf verwendet, nicht gespeichert) – oder leer lassen,
                wenn sie im FinTS-Zugang hinterlegt ist.
            </p>
            @if ($this->connections->isEmpty())
                <p class="mt-2 text-sm text-warning-600">
                    Noch kein FinTS-Zugang vorhanden – bitte zuerst unter „Bank → FinTS-Zugänge" anlegen.
                </p>
            @endif
        </div>

        {{-- 2. TAN bzw. App-Freigabe --}}
        @if ($step === 'tan')
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 dark:border-warning-500/30 dark:bg-warning-500/10">
                @if ($tanDecoupled)
                    <h3 class="font-semibold text-warning-800 dark:text-warning-200">Freigabe in der Banking-App erforderlich</h3>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ $tanChallenge }}</p>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                        Bitte den Auftrag in deiner Banking-App auf dem Handy freigeben und anschließend auf
                        „Freigabe prüfen" klicken.
                    </p>
                    <div class="mt-3">
                        <x-filament::button wire:click="checkApproval" wire:loading.attr="disabled" icon="heroicon-o-device-phone-mobile">
                            Freigabe prüfen
                            <span wire:loading wire:target="checkApproval">…</span>
                        </x-filament::button>
                    </div>
                @else
                    <h3 class="font-semibold text-warning-800 dark:text-warning-200">TAN erforderlich</h3>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ $tanChallenge }}</p>
                    <div class="mt-3 flex items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">TAN</label>
                            <input type="text" wire:model="tan" inputmode="numeric" autocomplete="one-time-code"
                                wire:keydown.enter="submitTan"
                                class="block w-48 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5">
                        </div>
                        <x-filament::button wire:click="submitTan" wire:loading.attr="disabled" icon="heroicon-o-check">
                            TAN bestätigen
                            <span wire:loading wire:target="submitTan">…</span>
                        </x-filament::button>
                    </div>
                @endif
            </div>
        @endif

        {{-- 3. Gefundene Konten auswählen & speichern --}}
        @if ($step === 'accounts' && count($discovered))
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <h3 class="font-semibold">Gefundene Konten</h3>
                <div class="mt-3 space-y-2">
                    @foreach ($discovered as $acc)
                        <label class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2 text-sm dark:border-white/5">
                            <input type="checkbox" wire:model="selected" value="{{ $acc['iban'] }}"
                                class="rounded border-gray-300 text-primary-600">
                            <span class="font-medium">{{ $acc['iban'] ?: $acc['account_number'] }}</span>
                            @if (! empty($acc['bic']))<span class="text-gray-500">· {{ $acc['bic'] }}</span>@endif
                            @if (! empty($acc['bank_code']))<span class="text-gray-400">· BLZ {{ $acc['bank_code'] }}</span>@endif
                        </label>
                    @endforeach
                </div>
                <div class="mt-4">
                    <x-filament::button wire:click="saveAccounts" icon="heroicon-o-check-circle" color="success">
                        Auswahl als Bankkonten speichern
                    </x-filament::button>
                </div>
            </div>
        @endif

        {{-- 4. Gespeicherte Konten + Umsatzabruf --}}
        @if ($connectionId && $this->savedAccounts->isNotEmpty())
            <div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-white/10">
                    Gespeicherte Konten dieses Zugangs
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->savedAccounts as $account)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <span class="font-medium">{{ $account->label }}</span>
                                <span class="text-gray-500"> · {{ $account->iban }}</span>
                                @if ($account->last_fetched_at)
                                    <span class="block text-xs text-gray-400">letzter Abruf: {{ $account->last_fetched_at->format('d.m.Y H:i') }}</span>
                                @endif
                            </div>
                            <x-filament::button wire:click="fetch({{ $account->id }})" wire:loading.attr="disabled"
                                size="sm" icon="heroicon-o-arrow-down-tray">
                                Umsätze abrufen
                                <span wire:loading wire:target="fetch({{ $account->id }})">…</span>
                            </x-filament::button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
