<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1.5rem;max-width:42rem;">
        <p style="font-size:.9rem;opacity:.7;">
            Bankdatei (MT940/.mta, CAMT.053/.xml oder CSV) hochladen. Format und – soweit ableitbar –
            das Konto werden automatisch erkannt; Dubletten werden übersprungen. Ist das Konto noch
            nicht vorhanden, wird nach einem Kontonamen gefragt. Die hochgeladene Datei wird nach dem
            Import automatisch gelöscht.
        </p>

        {{ $this->form }}

        @if ($awaitingName)
            <div style="border:1px solid #10b981;border-radius:.6rem;padding:1rem;background:rgba(16,185,129,.06);">
                <div style="font-weight:600;margin-bottom:.25rem;">Neues Bankkonto anlegen</div>
                <p style="font-size:.85rem;opacity:.7;margin-bottom:.6rem;">
                    Dieses Konto ist noch nicht vorhanden
                    @if (! empty($pendingAccount['iban'])) (IBAN {{ $pendingAccount['iban'] }})
                    @elseif (! empty($pendingAccount['account_number'])) (Konto {{ $pendingAccount['account_number'] }}, BLZ {{ $pendingAccount['bank_code'] }})
                    @endif. Bitte einen Kontonamen vergeben:
                </p>
                <div style="max-width:24rem;">
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="newAccountName" placeholder="z. B. VR Bank Fulda – Geschäftskonto" />
                    </x-filament::input.wrapper>
                    @error('newAccountName') <p style="color:#dc2626;margin-top:.35rem;font-size:.8rem;">{{ $message }}</p> @enderror
                </div>
                <div style="display:flex;gap:.5rem;margin-top:.8rem;">
                    <x-filament::button wire:click="confirmNewAccount" icon="heroicon-o-check-circle" color="success">
                        Konto anlegen & importieren
                    </x-filament::button>
                    <x-filament::button wire:click="cancelNewAccount" color="gray">Abbrechen</x-filament::button>
                </div>
            </div>
        @else
            <div>
                {{ $this->importAction }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
