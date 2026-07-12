<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        h1 { color: #059669; font-size: 20px; margin: 0 0 2px; }
        h2 { color: #059669; border-bottom: 2px solid #059669; padding-bottom: 3px; margin: 16px 0 6px; font-size: 13px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 5px; font-size: 10px; }
        .kpi td { border: 1px solid #e5e7eb; text-align: center; }
        .kpi .label { color: #6b7280; font-size: 9px; }
        .kpi .val { font-weight: bold; font-size: 13px; color: #059669; }
        .row td { border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        .lbl { color: #4b5563; }
        .num { text-align: right; white-space: nowrap; }
        .barwrap { background: #e5e7eb; height: 8px; width: 100%; border-radius: 4px; }
        .bar { height: 8px; border-radius: 4px; }
    </style>
</head>
<body>

<h1>Waschanlage – Controlling-Auswertung</h1>
<div class="meta">Jahr <strong>{{ $year }}</strong> · {{ $stationLabel }} · Erstellt am {{ $generatedAt }} · Karte &amp; PayPal zusammengefasst</div>

@php
    $k = $data['kpi'];
    $monate = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
    $wtage = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    $maxMonth = max(1, max($data['byMonth']));
    $maxWd = max(1, max(array_map(fn ($x) => $x['brutto'], $data['weekday'])));
    $maxProg = max(1, max(array_map(fn ($x) => $x['count'], $data['programs'] ?: [['count' => 1]])));
    $maxCust = max(1, max(array_map(fn ($x) => $x['brutto'], $data['customers'] ?: [['brutto' => 1]])));
    $barCell = function ($pct, $color) {
        $w = max(1, min(100, $pct));
        return '<div class="barwrap"><div class="bar" style="width:' . $w . '%;background:' . $color . ';"></div></div>';
    };
@endphp

<h2>Kennzahlen</h2>
<table class="kpi">
    <tr>
        <td><div class="label">Umsatz brutto</div><div class="val">{{ $money($k['brutto']) }}</div></td>
        <td><div class="label">USt 19 %</div><div class="val" style="color:#6b7280;">{{ $money($k['ust']) }}</div></td>
        <td><div class="label">Netto</div><div class="val" style="color:#374151;">{{ $money($k['netto']) }}</div></td>
        <td><div class="label">Wäschen</div><div class="val" style="color:#0ea5e9;">{{ number_format($k['count'], 0, ',', '.') }}</div></td>
        <td><div class="label">Ø Bon</div><div class="val" style="color:#8b5cf6;">{{ $money($k['avg']) }}</div></td>
        <td><div class="label">Kunden</div><div class="val" style="color:#f59e0b;">{{ number_format($k['kunden'], 0, ',', '.') }}</div></td>
        <td><div class="label">Gratis</div><div class="val" style="color:#b45309;">{{ number_format($k['gratis'], 0, ',', '.') }}</div></td>
    </tr>
</table>

<h2>Umsatzentwicklung {{ $year }}</h2>
<table>
    @foreach ($monate as $m => $lbl)
        <tr class="row">
            <td class="lbl" style="width:75px;">{{ $lbl }}</td>
            <td style="width:300px;">{!! $barCell(($data['byMonth'][$m] / $maxMonth) * 100, '#059669') !!}</td>
            <td class="num" style="width:80px;">{{ $money($data['byMonth'][$m]) }}</td>
        </tr>
    @endforeach
</table>

<h2>Meistverkaufte Waschprogramme</h2>
<table>
    @forelse ($data['programs'] as $p)
        <tr class="row">
            <td class="lbl" style="width:110px;">{{ $p['program'] }}</td>
            <td style="width:265px;">{!! $barCell(($p['count'] / $maxProg) * 100, '#0ea5e9') !!}</td>
            <td class="num" style="width:45px;"><strong>{{ $p['count'] }}×</strong></td>
            <td class="num" style="width:80px;">{{ $money($p['brutto']) }}</td>
        </tr>
    @empty
        <tr><td>Keine Daten.</td></tr>
    @endforelse
</table>

<h2>Umsatz nach Wochentag</h2>
<table>
    @foreach ($wtage as $wd => $lbl)
        <tr class="row">
            <td class="lbl" style="width:90px;">{{ $lbl }}</td>
            <td style="width:255px;">{!! $barCell(($data['weekday'][$wd]['brutto'] / $maxWd) * 100, '#8b5cf6') !!}</td>
            <td class="num" style="width:45px;">{{ $data['weekday'][$wd]['count'] }}×</td>
            <td class="num" style="width:80px;">{{ $money($data['weekday'][$wd]['brutto']) }}</td>
        </tr>
    @endforeach
</table>

<h2>Umsatz je Kunde (Top 15)</h2>
<table>
    @forelse ($data['customers'] as $c)
        <tr class="row">
            <td class="lbl" style="width:150px;overflow:hidden;">{{ $c['name'] }}</td>
            <td style="width:215px;">{!! $barCell(($c['brutto'] / $maxCust) * 100, '#f59e0b') !!}</td>
            <td class="num" style="width:45px;">{{ $c['count'] }}×</td>
            <td class="num" style="width:80px;"><strong>{{ $money($c['brutto']) }}</strong></td>
        </tr>
    @empty
        <tr><td>Keine Daten.</td></tr>
    @endforelse
</table>

</body>
</html>
