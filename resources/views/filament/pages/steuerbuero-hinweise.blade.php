<x-filament-panels::page>
    <div class="mx-auto w-full max-w-4xl space-y-6">

        {{-- Header-Banner --}}
        <div style="display:flex;align-items:flex-start;gap:1rem;padding:1.25rem;border:1px solid rgba(16,185,129,.25);border-radius:.85rem;background:rgba(16,185,129,.06);">
            <div style="display:flex;align-items:center;justify-content:center;width:3rem;height:3rem;flex-shrink:0;border-radius:.6rem;background:#059669;color:#fff;">
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" style="width:1.65rem;height:1.65rem;" />
            </div>
            <div style="display:flex;flex-direction:column;gap:.25rem;">
                <h2 style="font-size:1rem;font-weight:600;margin:0;">Hinweise an das Steuerbüro</h2>
                <p style="font-size:.875rem;margin:0;color:rgb(107,114,128);line-height:1.45;">
                    Karten je Bankkonto und Monat – per Drag & Drop sortierbar. Sie erscheinen
                    im Monatsbericht auf Seite 2 unter der Zusammenfassung, in genau dieser Reihenfolge.
                </p>
            </div>
        </div>

        <x-filament::section>
            {{ $this->form }}

            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(120,120,120,.15);">
                {{ $this->saveAction }}
            </div>
        </x-filament::section>

    </div>
</x-filament-panels::page>
