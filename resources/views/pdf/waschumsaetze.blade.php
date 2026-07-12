<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1f2937; margin: 0; }
        h2 { color: #059669; font-size: 18px; margin: 0 0 2px; }
        h3 { color: #059669; border-bottom: 2px solid #059669; padding-bottom: 3px; margin: 18px 0 6px; font-size: 13px; }
        .meta { color: #6b7280; font-size: 11px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th { background: #ecfdf5; text-align: left; padding: 5px 6px; border-bottom: 1px solid #a7f3d0; font-size: 10px; }
        td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        .num { text-align: right; white-space: nowrap; }
        .mono { font-family: DejaVu Sans Mono, monospace; }
        .sum td { font-weight: bold; border-top: 2px solid #059669; }
        .sub td { color: #4b5563; }
        .corr td { color: #b45309; }
        .pos { color: #059669; }
        .badge { font-size: 9px; padding: 1px 5px; border-radius: 3px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>

<h2>Waschumsätze – Kassen-Liste</h2>
<div class="meta">Zeitraum: <strong>{{ $periodLabel }}</strong> · Erstellt am {{ $generatedAt }}</div>

@forelse ($kassenListe as $block)
    <h3>{{ $block['business']->display_label }}</h3>
    <table>
        <thead>
            <tr>
                <th style="width:50px;">Menge</th>
                <th>Artikel</th>
                <th style="width:185px;">EAN</th>
                <th class="num" style="width:65px;">Einzel (VK)</th>
                <th class="num" style="width:75px;">Gesamt</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($block['lines'] as $line)
                <tr>
                    <td><strong>{{ $line['qty'] }} ×</strong></td>
                    <td>{{ $line['name'] }}</td>
                    <td class="mono">
                        @php $bc = \App\Services\Wash\BarcodeGenerator::ean13DataUri($line['ean'], 2, 34); @endphp
                        @if ($bc)
                            <img src="{{ $bc }}" style="height:26px;"><br>{{ $line['ean'] }}
                        @else
                            {{ $line['ean'] ?: '—' }}
                        @endif
                    </td>
                    <td class="num">{{ $line['vk'] !== null ? $money($line['vk']) : '—' }}</td>
                    <td class="num">{{ $money($line['zwischensumme']) }}</td>
                </tr>
            @endforeach
            @if (abs($block['correction']) >= 0.005)
                <tr class="corr">
                    <td></td>
                    <td colspan="3">Preis-/Rabattkorrektur (auf Geldeingang)</td>
                    <td class="num">{{ $money($block['correction']) }}</td>
                </tr>
            @endif
            <tr class="sum">
                <td colspan="4">Summe = Geldeingang ({{ $block['count'] }} Wäschen)</td>
                <td class="num pos">{{ $money($block['sum_ist']) }}</td>
            </tr>
            <tr class="sub">
                <td colspan="4">davon USt 19 %</td>
                <td class="num">{{ $money($block['ust']) }}</td>
            </tr>
            <tr class="sub">
                <td colspan="4">Netto</td>
                <td class="num">{{ $money($block['net']) }}</td>
            </tr>
        </tbody>
    </table>
@empty
    <p style="opacity:.6;">Keine bezahlten Wäschen im Zeitraum.</p>
@endforelse

@if ($freeDoc->isNotEmpty())
    <h3>Gratis-Wäschen (0 €) – nur zur Doku, kein Umsatz</h3>
    <table>
        <thead>
            <tr>
                <th style="width:70px;">Datum</th>
                <th style="width:110px;">Station</th>
                <th>Programm</th>
                <th style="width:110px;">Kennzeichen</th>
                <th style="width:110px;">Kategorie</th>
            </tr>
        </thead>
        <tbody>
            @php $kat = ['eigen' => 'Eigenfahrzeug', 'mitarbeiter' => 'Mitarbeiter', 'test' => 'Testwäsche']; @endphp
            @foreach ($freeDoc as $f)
                <tr>
                    <td>{{ $f->payment_date?->format('d.m.Y') }}</td>
                    <td>{{ $f->business?->short_name ?: ($f->is_subscription ? 'Abo (offen)' : '—') }}</td>
                    <td>{{ $f->program ?: '—' }}</td>
                    <td>{{ $f->plate ?: '—' }}</td>
                    <td>{{ $kat[$f->free_category] ?? 'unklassifiziert' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

</body>
</html>
