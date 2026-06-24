<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Erzeugt den Pendelordner für den Steuerberater: Deckblatt, Zusammenfassung,
            chronologische Umsatzliste und – in exakter Umsatzreihenfolge – die
            Original-Belege hinter dem jeweiligen Umsatz.
        </p>

        {{ $this->form }}

        <div>
            {{ $this->generateAction }}
        </div>
    </div>
</x-filament-panels::page>
