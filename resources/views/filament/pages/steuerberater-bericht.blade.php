<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-6">

        {{-- Header-Banner --}}
        <div class="flex items-start gap-4 rounded-xl border border-primary-200 bg-gradient-to-br from-primary-50 to-white p-5 dark:border-primary-900/40 dark:from-primary-950/40 dark:to-gray-900">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white shadow-sm">
                <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-7 w-7" />
            </div>
            <div class="space-y-1">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Pendelordner für den Steuerberater</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Deckblatt, Zusammenfassung, chronologische Umsatzliste und – in exakter
                    Umsatzreihenfolge – die Original-Belege hinter dem jeweiligen Umsatz.
                </p>
            </div>
        </div>

        {{-- Formular + Aktionen --}}
        <x-filament::section>
            <x-slot name="heading">Bericht zusammenstellen</x-slot>
            <x-slot name="description">Zeitraum und – optional – Betrieb bzw. einzelnes Bankkonto wählen.</x-slot>

            {{ $this->form }}

            <div class="mt-6 flex flex-col gap-4 border-t border-gray-100 pt-5 dark:border-white/10">
                <div class="flex flex-wrap items-center gap-3">
                    {{ $this->generateAction }}
                    {{ $this->generatePerAccountAction }}
                </div>

                <div class="flex items-start gap-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 shrink-0 text-gray-400" />
                    <span>
                        <strong class="font-medium text-gray-700 dark:text-gray-300">Pro Konto je PDF (ZIP)</strong>
                        erzeugt für jedes Konto mit Umsätzen im Zeitraum einen eigenen Pendelordner und
                        bündelt sie als ZIP. Die Konto-Auswahl oben wird dabei ignoriert.
                    </span>
                </div>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
