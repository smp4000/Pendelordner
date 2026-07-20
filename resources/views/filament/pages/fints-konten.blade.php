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
                        <x-filament::input type="password" wire:model="pin" placeholder="PIN eingeben"
                            autocomplete="new-password" data-1p-ignore data-lpignore="true" />
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

                <x-filament::button wire:click="diagnose" color="gray" icon="heroicon-o-information-circle"
                    :disabled="! $connectionId" class="ml-2">
                    Diagnose (was wird gesendet?)
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
                <x-slot name="description">Konten auswählen, die als Bankkonten übernommen werden sollen.</x-slot>

                {{-- Kopfzeile: Zähler + Alle/Keine --}}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
                    <div style="font-size:.85rem;opacity:.75;">
                        <strong>{{ count($selected) }}</strong> von {{ count($discovered) }} ausgewählt
                    </div>
                    <div style="display:flex;gap:.4rem;">
                        <button type="button" wire:click="selectAllAccounts"
                            style="padding:.3rem .7rem;border:1px solid rgba(16,185,129,.5);color:#059669;background:transparent;border-radius:.4rem;cursor:pointer;font-size:.8rem;">Alle</button>
                        <button type="button" wire:click="clearAccounts"
                            style="padding:.3rem .7rem;border:1px solid rgba(120,120,120,.35);background:transparent;border-radius:.4rem;cursor:pointer;font-size:.8rem;">Keine</button>
                    </div>
                </div>

                {{-- Karten-Raster: je Konto eine Kachel --}}
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:.6rem;">
                    @foreach ($discovered as $acc)
                        @php
                            $iban = $acc['iban'] ?? '';
                            $ibanFmt = $iban !== '' ? trim(chunk_split($iban, 4, ' ')) : ($acc['account_number'] ?? '—');
                            $isSel = in_array($iban, $selected, true);
                        @endphp
                        <label style="display:flex;align-items:center;gap:.7rem;padding:.6rem .75rem;border:1px solid {{ $isSel ? '#10b981' : 'rgba(120,120,120,.25)' }};border-radius:.6rem;cursor:pointer;transition:.15s;{{ $isSel ? 'background:rgba(16,185,129,.08);' : '' }}">
                            <input type="checkbox" wire:model.live="selected" value="{{ $iban }}"
                                style="width:1.05rem;height:1.05rem;accent-color:#10b981;flex:0 0 auto;cursor:pointer;">
                            <div style="min-width:0;">
                                <div style="font-weight:600;font-size:.88rem;font-variant-numeric:tabular-nums;letter-spacing:.01em;">{{ $ibanFmt }}</div>
                                <div style="font-size:.74rem;opacity:.6;margin-top:.12rem;">
                                    @if (! empty($acc['bic'])){{ $acc['bic'] }}@endif
                                    @if (! empty($acc['bic']) && ! empty($acc['bank_code'])) · @endif
                                    @if (! empty($acc['bank_code']))BLZ {{ $acc['bank_code'] }}@endif
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>

                <div style="margin-top:1rem;">
                    <x-filament::button wire:click="saveAccounts" icon="heroicon-o-check-circle" color="success"
                        :disabled="count($selected) === 0">
                        Auswahl als Bankkonten speichern ({{ count($selected) }})
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- 4. Gespeicherte Konten + Umsatzabruf --}}
        @if ($connectionId && $this->savedAccounts->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Gespeicherte Konten dieses Zugangs</x-slot>

                <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;margin-bottom:.5rem;">
                    <span style="font-size:.8rem;opacity:.7;">Alle Konten inkrementell ab dem letzten Abruf holen.</span>
                    <x-filament::button wire:click="fetchAll" wire:loading.attr="disabled" wire:target="fetchAll"
                        size="sm" color="success" icon="heroicon-o-arrow-down-tray">
                        Alle Konten abrufen
                        <span wire:loading wire:target="fetchAll">…</span>
                    </x-filament::button>
                </div>

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
