{{-- Trennseite vor den Belegen eines Umsatzes (Modul 12) --}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1f2937; }
        .bar { background: #059669; color: #fff; padding: 14px 18px; border-radius: 6px; }
        .bar h2 { margin: 0; font-size: 18px; }
        .bar .amount { float: right; font-size: 18px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .label { color: #6b7280; width: 160px; }
        .receipts { margin-top: 20px; }
        .receipts th { background: #ecfdf5; text-align: left; padding: 6px; font-size: 10px; }
        .receipts td { font-size: 10px; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <div class="bar">
        <span class="amount">{{ $money($t->amount) }}</span>
        <h2>{{ $t->counterparty ?: 'Bankumsatz' }}</h2>
    </div>

    <table>
        <tr><td class="label">Buchungsdatum</td><td>{{ $t->booking_date?->format('d.m.Y') }}</td></tr>
        <tr><td class="label">Verwendungszweck</td><td>{{ $t->purpose ?: '—' }}</td></tr>
        <tr><td class="label">Kategorie</td><td>{{ $t->category?->name ?? '—' }}</td></tr>
        <tr><td class="label">Kostenstelle</td><td>{{ $t->costCenter?->name ?? '—' }}</td></tr>
        <tr><td class="label">Bankkonto</td><td>{{ $t->bankAccount?->label }}</td></tr>
    </table>

    <div class="receipts">
        <strong>Zugeordnete Belege ({{ $t->receipts->count() }})</strong>
        <table>
            <thead>
                <tr><th>Rechnungs-Nr.</th><th>Lieferant</th><th>Datum</th><th class="num">Betrag</th><th class="num">zugeordnet</th></tr>
            </thead>
            <tbody>
                @foreach ($t->receipts as $r)
                    <tr>
                        <td>{{ $r->invoice_number ?: '—' }}</td>
                        <td>{{ $r->supplier?->name ?? '—' }}</td>
                        <td>{{ $r->invoice_date?->format('d.m.Y') ?? '—' }}</td>
                        <td class="num">{{ $money($r->gross_amount) }}</td>
                        <td class="num">{{ $money($r->pivot->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
