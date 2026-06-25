<x-filament-panels::page>
    <div style="display:flex;flex-direction:column;gap:1.5rem;max-width:42rem;">
        <p style="font-size:.9rem;opacity:.7;">
            Eine oder mehrere Bankdateien (MT940/.mta, CAMT.053/.xml oder CSV) hochladen. Die Dateien werden
            nacheinander verarbeitet. Format und – soweit ableitbar – das Konto werden je Datei automatisch
            erkannt; Dubletten werden übersprungen. Ist ein Konto noch nicht vorhanden, wird es automatisch
            angelegt (unter „Bankkonten" umbenennbar). Die hochgeladenen Dateien werden nach dem Import
            automatisch gelöscht.
        </p>

        {{ $this->form }}

        <div>
            {{ $this->importAction }}
        </div>
    </div>
</x-filament-panels::page>
