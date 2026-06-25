<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1.5rem;max-width:42rem;">
        <p style="font-size:.9rem;opacity:.7;">
            Eine oder mehrere Bankdateien (MT940/.mta, CAMT.053/.xml oder CSV) hochladen. Die Dateien werden
            nacheinander verarbeitet. Format und – soweit ableitbar – das Konto werden je Datei automatisch
            erkannt; Dubletten werden übersprungen. Ist ein Konto noch nicht vorhanden, wird ein Kontoname
            vorgeschlagen, den du vor dem Import ändern kannst. Die hochgeladenen Dateien werden nach dem
            Import automatisch gelöscht.
        </p>

        {{ $this->form }}

        @if ($awaitingNames)
            <div style="border:1px solid #10b981;border-radius:.6rem;padding:1rem;background:rgba(16,185,129,.06);">
                <div style="font-weight:600;margin-bottom:.5rem;">Neue Bankkonten anlegen</div>
                <p style="font-size:.85rem;opacity:.7;margin-bottom:.8rem;">
                    Für die folgenden, noch nicht vorhandenen Konten bitte den Namen prüfen und ggf. ändern:
                </p>

                <div style="display:flex;flex-direction:column;gap:.8rem;">
                    @foreach ($pendingAccounts as $i => $acc)
                        <div>
                            <label style="display:block;font-size:.78rem;opacity:.7;margin-bottom:.2rem;">
                                {{ $acc['hint'] ?? 'Neues Konto' }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="pendingAccounts.{{ $i }}.name"
                                    placeholder="z. B. VR Bank Fulda – Geschäftskonto" />
                            </x-filament::input.wrapper>
                            @error('pendingAccounts.' . $i . '.name')
                                <p style="color:#dc2626;margin-top:.3rem;font-size:.8rem;">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div style="display:flex;gap:.5rem;margin-top:1rem;">
                    <x-filament::button wire:click="confirmNames" icon="heroicon-o-check-circle" color="success">
                        Konten anlegen & importieren
                    </x-filament::button>
                    <x-filament::button wire:click="cancelNames" color="gray">Abbrechen</x-filament::button>
                </div>
            </div>
        @else
            <div>
                {{ $this->importAction }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
