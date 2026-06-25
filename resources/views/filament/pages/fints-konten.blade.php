<x-filament-panels::page>
    <div class="space-y-6">

        {{-- 1. Zugang wählen + PIN --}}
        <x-filament::section>
            <x-slot name="heading">FinTS-Zugang & PIN</x-slot>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">FinTS-Zugang</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="connectionId">
                            <option value="">– bitte wählen –</option>
                            @foreach ($this->connections as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">PIN</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="password" wire:model="pin" placeholder="PIN eingeben" autocomplete="off" />
                    </x-filament::input.wrapper>
                    <p class="mt-1 text-xs text-gray-500">
                        Wird nur für den Abruf verwendet, nicht gespeichert. Leer lassen, wenn im Zugang hinterlegt.
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <x-filament::button wire:click="discover" wire:loading.attr="disabled"
                    icon="heroicon-o-magnifying-glass" :disabled="! $connectionId">
                    Konten abrufen
                    <span wire:loading wire:target="discover">…</span>
                </x-filament::button>
            </div>

            @if ($this->connections->isEmpty())
                <p class="mt-2 text-sm text-warning-600">
                    Noch kein FinTS-Zugang vorhanden – bitte zuerst unter „Bank → FinTS-Zugänge" anlegen.
                </p>
            @endif
        </x-filament::section>

        {{-- 2. TAN bzw. App-Freigabe --}}
        @if ($step === 'tan')
            <x-filament::section>
                @if ($tanDecoupled)
                    <x-slot name="heading">Freigabe in der Banking-App erforderlich</x-slot>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $tanChallenge }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Bitte den Auftrag in deiner Banking-App auf dem Handy freigeben und anschließend auf
                        „Freigabe prüfen" klicken.
                    </p>
                    <div class="mt-4">
                        <x-filament::button wire:click="checkApproval" wire:loading.attr="disabled"
                            icon="heroicon-o-device-phone-mobile">
                            Freigabe prüfen
                            <span wire:loading wire:target="checkApproval">…</span>
                        </x-filament::button>
                    </div>
                @else
                    <x-slot name="heading">TAN erforderlich</x-slot>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $tanChallenge }}</p>
                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">TAN</label>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="tan" inputmode="numeric"
                                    autocomplete="one-time-code" wire:keydown.enter="submitTan" />
                            </x-filament::input.wrapper>
                        </div>
                        <x-filament::button wire:click="submitTan" wire:loading.attr="disabled" icon="heroicon-o-check">
                            TAN bestätigen
                            <span wire:loading wire:target="submitTan">…</span>
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- 3. Gefundene Konten auswählen & speichern --}}
        @if ($step === 'accounts' && count($discovered))
            <x-filament::section>
                <x-slot name="heading">Gefundene Konten</x-slot>

                <div class="space-y-2">
                    @foreach ($discovered as $acc)
                        <label class="flex items-center gap-3 text-sm">
                            <x-filament::input.checkbox wire:model="selected" value="{{ $acc['iban'] }}" />
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
            </x-filament::section>
        @endif

        {{-- 4. Gespeicherte Konten + Umsatzabruf --}}
        @if ($connectionId && $this->savedAccounts->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Gespeicherte Konten dieses Zugangs</x-slot>

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($this->savedAccounts as $account)
                        <div class="flex items-center justify-between gap-3 py-3 text-sm">
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
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
