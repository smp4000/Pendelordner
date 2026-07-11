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
        .memo { margin-top: 4px; font-weight: bold; color: #92400e; font-size: 10px;
            background-color: #fef3c7; border: 1px solid #f59e0b; border-radius: 4px;
            padding: 3px 6px; }
        .memo-label { color: #b45309; }
        .note-card { border: 1px solid #a7f3d0; border-radius: 5px; padding: 6px 9px; margin-top: 8px; background: #f0fdf4; }
        .note-head { font-weight: bold; color: #065f46; font-size: 11px; margin-bottom: 3px; }
        .note-lines { width: 100%; border-collapse: collapse; }
        .note-lines td { border: none; padding: 1px 4px; font-size: 10px; color: #1f2937; vertical-align: top; }
        .note-lines .note-amount { width: 90px; white-space: nowrap; font-weight: bold; }
        .splits { width: 100%; margin: 1px 0 2px; border-collapse: collapse; }
        .splits td { border: none; border-bottom: 1px dotted #e5e7eb; padding: 1.5px 6px; font-size: 9.5px; color: #4b5563; }
        .splits .split-label { color: #065f46; font-weight: bold; width: 62px; }
        tr.has-splits td { border-bottom: none; }
        tr.split-row td { padding-top: 0; }
    </style>
</head>
<body>

@php $section = $section ?? 'all'; @endphp

@if ($section !== 'list')
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
                                <td>{!! nl2br(e($line->text)) !!}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach
    @endif

    @if (! empty($steuerDocs) && $steuerDocs->isNotEmpty())
        <h3 style="margin-top:26px;">Steuerbüro-Dateien (angehängt, je Monat ab 1)</h3>
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">Nr.</th>
                    <th style="width:80px;">Monat</th>
                    <th style="width:150px;">Kategorie</th>
                    <th>Datei</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($steuerDocs as $doc)
                    <tr>
                        <td><strong>{{ $steuerNumbers[$doc->id] ?? '–' }}</strong></td>
                        <td>{{ $doc->period?->format('m/Y') }}</td>
                        <td>{{ $doc->category }}</td>
                        <td>{{ $doc->file_name }}
                            @if (trim((string) $doc->note) !== '')
                                <div class="memo"><span class="memo-label">Hinweis:</span> {{ $doc->note }}</div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endif

@if ($section !== 'intro')
{{-- Seite 3 ff.: Umsatzliste (Zeitraum + Konto in der Überschrift) --}}
<h3>Umsätze {{ $periodLabel }}@if (! empty($account)) · Konto {{ $account->label }}@if ($account->iban) ({{ $account->iban }})@endif @endif</h3>
<table>
    <thead>
        <tr>
            <th style="width:60px;">Datum</th>
            <th>Empfänger / Verwendungszweck</th>
            <th style="width:80px;">Kategorie</th>
            <th style="width:70px;">Kostenstelle</th>
            <th style="width:85px;">Konto (eDTAS)</th>
            <th class="num" style="width:75px;">Betrag</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $t)
            @php $hasSplits = $t->accountAssignments->isNotEmpty(); @endphp
            <tr @if ($hasSplits) class="has-splits" @endif>
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
                        <div class="memo"><span class="memo-label">Hinweis:</span> {{ $t->accountant_note }}</div>
                    @endif
                </td>
                {{-- Bei einer Aufteilung sind die Split-Zeilen maßgeblich –
                     Kategorie und Konto der Hauptzuordnung ausblenden. --}}
                <td>{{ $hasSplits ? '' : ($t->category?->name ?? '—') }}</td>
                <td>{{ $t->costCenter?->name ?? '—' }}</td>
                <td>
                    @if ($hasSplits)
                        <span class="receipts">→ Aufteilung</span>
                    @elseif ($t->ledgerAccount)
                        <strong>{{ $t->ledgerAccount->number }}</strong>
                        <br><span class="receipts">{{ \Illuminate\Support\Str::limit($t->ledgerAccount->name, 22) }}</span>
                    @else
                        —
                    @endif
                </td>
                <td class="num {{ $t->amount < 0 ? 'neg' : 'pos' }}">{{ $money($t->amount) }}</td>
            </tr>
            {{-- Aufteilung in voller Breite unter dem Umsatz (gut lesbar). --}}
            @if ($hasSplits)
                <tr class="split-row">
                    <td></td>
                    <td colspan="5">
                        <table class="splits">
                            @php
                                // Gleiches Sachkonto + gleicher USt-Satz + gleiche Rechnungsnummer
                                // zu EINER Zeile zusammenfassen. Positionen OHNE Rechnungsnummer
                                // (aggregierte Aufteilung) werden je Konto/Satz addiert; Positionen
                                // MIT Rechnungsnummer bleiben je Rechnung getrennt sichtbar.
                                $groupedSplits = $t->accountAssignments
                                    ->groupBy(fn ($a) => $a->ledger_account_id . '|' . ($a->tax_rate !== null ? (float) $a->tax_rate : 'x') . '|' . trim((string) $a->booking_text))
                                    ->map(fn ($rows) => (object) [
                                        'ledgerAccount' => $rows->first()->ledgerAccount,
                                        'tax_rate' => $rows->first()->tax_rate,
                                        'booking_text' => trim((string) $rows->first()->booking_text),
                                        'amount' => $rows->sum(fn ($r) => (float) $r->amount),
                                    ])
                                    ->values();
                            @endphp
                            @foreach ($groupedSplits as $a)
                                @php
                                    // Gruppen-Anhang aus dem Kontonamen entfernen (z. B. " A,Karten, …").
                                    $splitName = $a->ledgerAccount
                                        ? trim((string) preg_replace('/\s+[A-D],.*$/u', '', $a->ledgerAccount->name))
                                        : 'Sachkonto fehlt!';
                                @endphp
                                <tr>
                                    <td class="split-label">{{ $loop->first ? 'Aufteilung:' : '' }}</td>
                                    <td>{{ $a->ledgerAccount ? $a->ledgerAccount->number . ' – ' . \Illuminate\Support\Str::limit($splitName, 70) : $splitName }}@if ($a->booking_text !== '') <span class="receipts">· Rg. {{ $a->booking_text }}</span>@endif</td>
                                    <td style="width:42px;" class="num">{{ $a->tax_rate !== null ? rtrim(rtrim(number_format((float)$a->tax_rate,2,',','.'),'0'),',').' %' : '' }}</td>
                                    <td style="width:78px;" class="num">{{ $money($a->amount) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>
            @endif
        @endforeach
    </tbody>
</table>

@php
    // Alle Umsätze mit einem Hinweis für das Steuerbüro (accountant_note).
    $hinweisRows = $transactions->filter(fn ($t) => trim((string) $t->accountant_note) !== '')->values();
@endphp
@if ($hinweisRows->isNotEmpty())
<h3 style="page-break-before: always;">Hinweise zu einzelnen Umsätzen</h3>
<table>
    <thead>
        <tr>
            <th style="width:70px;">Datum</th>
            <th>Umsatz</th>
            <th class="num" style="width:80px;">Betrag</th>
            <th style="width:42%;">Hinweis</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($hinweisRows as $t)
            <tr>
                <td>{{ $t->booking_date?->format('d.m.Y') }}</td>
                <td>
                    <strong>{{ $t->counterparty ?: '—' }}</strong>
                    @if ($t->clean_purpose !== '')<br><span class="receipts">{{ \Illuminate\Support\Str::limit($t->clean_purpose, 70) }}</span>@endif
                </td>
                <td class="num {{ $t->amount < 0 ? 'neg' : 'pos' }}">{{ $money($t->amount) }}</td>
                <td><div class="memo" style="margin-top:0;">{{ $t->accountant_note }}</div></td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif
@endif

</body>
</html>
