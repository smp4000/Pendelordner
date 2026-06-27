{{-- Steuerberater-Pendelordner – Deckblatt, Zusammenfassung, chronologische Umsatzliste (Modul 12) --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        .page-break { page-break-after: always; }
        .cover { text-align: center; padding-top: 220px; }
        .cover h1 { font-size: 30px; color: #059669; margin-bottom: 8px; }
        .cover h2 { font-size: 20px; color: #374151; font-weight: normal; }
        .cover .addr { margin-top: 6px; font-size: 15px; color: #374151; line-height: 1.4; }
        .cover .meta { margin-top: 40px; font-size: 14px; color: #6b7280; }
        h3 { color: #059669; border-bottom: 2px solid #059669; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #ecfdf5; text-align: left; padding: 6px; border-bottom: 1px solid #a7f3d0; font-size: 10px; }
        td { padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .neg { color: #dc2626; }
        .pos { color: #059669; }
        .summary td { padding: 8px; font-size: 13px; }
        .summary .label { color: #6b7280; }
        .summary .value { text-align: right; font-weight: bold; }
        .receipts { color: #6b7280; font-size: 9px; }
        .beleg-nr { display: inline-block; margin-top: 2px; background: #059669; color: #fff;
                    font-size: 9px; font-weight: bold; padding: 1px 5px; border-radius: 3px; }
        .memo { margin-top: 3px; font-weight: bold; color: #b45309; font-size: 10px; }
        .note-card { border: 1px solid #a7f3d0; border-radius: 5px; padding: 6px 9px; margin-top: 8px; background: #f0fdf4; }
        .note-head { font-weight: bold; color: #065f46; font-size: 11px; margin-bottom: 3px; }
        .note-lines { width: 100%; border-collapse: collapse; }
        .note-lines td { border: none; padding: 1px 4px; font-size: 10px; color: #1f2937; vertical-align: top; }
        .note-lines .note-amount { width: 90px; white-space: nowrap; font-weight: bold; }
        .splits { width: 100%; margin-top: 3px; border-collapse: collapse; }
        .splits td { border: none; border-bottom: 1px dotted #e5e7eb; padding: 1px 4px; font-size: 9px; color: #4b5563; }
    </style>
</head>
<body>

{{-- Seite 1: Deckblatt --}}
<div class="cover page-break">
    <h2>{{ $business?->name ?? 'Alle Betriebe' }}</h2>
    @if ($business && ($business->street || $business->postal_code || $business->city))
        <div class="addr">
            @if ($business->street){{ $business->street }}<br>@endif
            {{ trim(($business->postal_code ?? '') . ' ' . ($business->city ?? '')) }}
        </div>
    @endif
    @if (! empty($account))
        <h2 style="font-size:16px;margin-top:4px;">Konto: {{ $account->label }}@if($account->iban) ({{ $account->iban }})@endif</h2>
    @endif
    <div class="meta">
        Monatsbericht für den Steuerberater<br>
        <strong>{{ $periodLabel }}</strong><br><br>
        Erstellt am {{ $generatedAt }}
    </div>
</div>

{{-- Seite 2: Zusammenfassung --}}
<div class="page-break">
    <h3>Zusammenfassung – {{ $periodLabel }}</h3>
    <table class="summary">
        <tr><td class="label">Anzahl Umsätze</td><td class="value">{{ $stats['count'] }}</td></tr>
        <tr><td class="label">Summe Eingänge</td><td class="value pos">{{ $money($stats['income']) }}</td></tr>
        <tr><td class="label">Summe Ausgänge</td><td class="value neg">{{ $money($stats['expense']) }}</td></tr>
        <tr><td class="label">Anzahl zugeordneter Belege</td><td class="value">{{ $stats['receipts'] }}</td></tr>
        <tr><td class="label">davon als PDF angehängt</td><td class="value">{{ $stats['appendedFiles'] ?? 0 }}</td></tr>
        <tr><td class="label">Umsätze ohne Beleg</td><td class="value neg">{{ $stats['withoutReceipt'] }}</td></tr>
        <tr><td class="label">Nicht geprüfte Umsätze</td><td class="value">{{ $stats['unreviewed'] }}</td></tr>
    </table>

    @if (! empty($reportNotes) && $reportNotes->isNotEmpty())
        <h3 style="margin-top:26px;">Hinweise an das Steuerbüro</h3>
        @foreach ($reportNotes as $note)
            <div class="note-card">
                @if ($note->heading)<div class="note-head">{{ $note->heading }}</div>@endif
                @if ($note->lines->isNotEmpty())
                    <table class="note-lines">
                        @foreach ($note->lines as $line)
                            <tr>
                                <td class="note-amount">{{ $line->amount }}</td>
                                <td>{{ $line->text }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach
    @endif
</div>

{{-- Seite 3 ff.: Chronologische Umsatzliste --}}
<h3>Chronologische Umsatzliste</h3>
<table>
    <thead>
        <tr>
            <th style="width:60px;">Datum</th>
            <th>Empfänger / Verwendungszweck</th>
            <th style="width:80px;">Kategorie</th>
            <th style="width:70px;">Kostenstelle</th>
            <th style="width:85px;">Konto (SKR/edtas)</th>
            <th class="num" style="width:75px;">Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $t)
            <tr>
                <td>{{ $t->booking_date?->format('d.m.Y') }}</td>
                <td>
                    <strong>{{ $t->counterparty ?: '—' }}</strong>
                    @if ($t->clean_purpose !== '')<br><span class="receipts">{{ \Illuminate\Support\Str::limit($t->clean_purpose, 80) }}</span>@endif
                    @php
                        $nums = $t->receipts
                            ->map(fn ($r) => $receiptNumbers[$r->id] ?? null)
                            ->filter()
                            ->sort()
                            ->values();
                    @endphp
                    @if ($nums->isNotEmpty())
                        <br><span class="beleg-nr">Beleg {{ $nums->implode(', ') }}</span>
                    @endif
                    @if (trim((string) $t->accountant_note) !== '')
                        <div class="memo">{{ $t->accountant_note }}</div>
                    @endif
                    @if ($t->accountAssignments->isNotEmpty())
                        <table class="splits">
                            @foreach ($t->accountAssignments as $a)
                                <tr>
                                    <td>{{ $a->category?->name ?? 'Ohne Kategorie' }}@if($a->booking_text) · {{ \Illuminate\Support\Str::limit($a->booking_text, 30) }}@endif</td>
                                    <td style="width:60px;" class="num">{{ $money($a->amount) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
                <td>{{ $t->category?->name ?? '—' }}</td>
                <td>{{ $t->costCenter?->name ?? '—' }}</td>
                <td>
                    @if ($t->ledgerAccount)
                        <strong>{{ $t->ledgerAccount->number }}</strong>
                        <br><span class="receipts">{{ \Illuminate\Support\Str::limit($t->ledgerAccount->name, 22) }}</span>
                    @else
                        —
                    @endif
                </td>
                <td class="num {{ $t->amount < 0 ? 'neg' : 'pos' }}">{{ $money($t->amount) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
