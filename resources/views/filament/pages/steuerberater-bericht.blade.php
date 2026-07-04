<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-6">

        {{-- Header-Banner --}}
        <div style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem;border:1px solid rgba(16,185,129,.25);border-radius:.85rem;background:rgba(16,185,129,.06);">
            <div style="display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex-shrink:0;border-radius:.6rem;background:#059669;color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.1);">
                <x-filament::icon icon="heroicon-o-document-arrow-down" style="width:1.65rem;height:1.65rem;" />
            </div>
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <h2 style="font-size:1rem;font-weight:600;margin:0;">Pendelordner für den Steuerberater</h2>
                <p style="font-size:.875rem;margin:0;color:rgb(107,114,128);line-height:1.45;">
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

            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(120,120,120,.15);display:flex;flex-direction:column;gap:1rem;">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:.75rem;">
                    {{ $this->generateAction }}
                    {{ $this->generatePerAccountAction }}
                </div>

                <div style="display:flex;align-items:flex-start;gap:.6rem;padding:.75rem .9rem;border-radius:.6rem;background:rgba(120,120,120,.07);font-size:.8rem;line-height:1.45;color:rgb(107,114,128);">
                    <x-filament::icon icon="heroicon-o-information-circle" style="width:1.1rem;height:1.1rem;flex-shrink:0;margin-top:.05rem;opacity:.6;" />
                    <span>
                        <strong style="font-weight:600;color:rgb(75,85,99);">Pro Konto je PDF (ZIP)</strong>
                        erzeugt für jedes Konto mit Umsätzen im Zeitraum einen eigenen Pendelordner und
                        bündelt sie als ZIP. Die Konto-Auswahl oben wird dabei ignoriert. Zur Sicherung
                        enthält das ZIP zusätzlich <strong style="font-weight:600;color:rgb(75,85,99);">alle
                        zugeordneten Belege</strong> – auch die nicht im Bericht ausgedruckten
                        (Ordner „Belege_nicht_im_Bericht").
                    </span>
                </div>
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
