@php
    $offeneHinweise = auth()->check()
        ? \App\Models\BankTransaction::query()->openNote()->count()
        : 0;
@endphp

@if ($offeneHinweise > 0)
    <a href="{{ \Filament\Pages\Dashboard::getUrl() }}"
        title="{{ $offeneHinweise }} offene(r) Hinweis(e) – zum Dashboard"
        style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .7rem;margin-right:.25rem;
               border-radius:9999px;background:rgba(217,119,6,.12);color:rgb(180,83,9);
               font-size:.8rem;font-weight:600;white-space:nowrap;text-decoration:none;
               border:1px solid rgba(217,119,6,.35);">
        <span aria-hidden="true">⚠</span>
        <span>{{ $offeneHinweise }} offene {{ $offeneHinweise === 1 ? 'Hinweis' : 'Hinweise' }}</span>
    </a>
@endif
