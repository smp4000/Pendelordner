<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1.5rem;max-width:42rem;">
        <p style="font-size:.9rem;opacity:.7;">
            Bankdatei (MT940/.mta, CAMT.053/.xml oder CSV) hochladen. Format und – bei MT940 – das
            Konto werden automatisch erkannt; Dubletten werden übersprungen. Die hochgeladene Datei
            wird nach dem Import automatisch wieder gelöscht.
        </p>

        {{ $this->form }}

        <div>
            {{ $this->importAction }}
        </div>
    </div>
</x-filament-panels::page>
