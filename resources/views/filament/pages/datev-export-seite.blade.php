<x-filament-panels::page>
    <div class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Erzeugt einen DATEV-EXTF-Buchungsstapel aus den kontierten Bankumsätzen
            des gewählten Zeitraums. Voraussetzung: die Umsätze wurden zuvor
            kontiert (Bankumsätze → Massenaktion „Kontieren").
        </p>

        {{ $this->form }}

        <div>
            {{ $this->generateAction }}
        </div>
    </div>
</x-filament-panels::page>
