<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use Illuminate\Console\Command;

/**
 * Diagnose für die Sammelzahlungs-/Avis-Erkennung: zeigt für einen Umsatz
 * (per Betrag gesucht), welche offenen Belege es gibt, ob deren
 * Rechnungsnummern im Verwendungszweck/Avis auftauchen und ob die Erkennung
 * greift. Hilft festzustellen, warum die Zuordnung (nicht) vorgeschlagen wird.
 */
class SammelzahlungDiagnose extends Command
{
    protected $signature = 'belege:sammel-diagnose {betrag : Umsatzbetrag, z. B. 524,37}';

    protected $description = 'Diagnose, warum eine Sammelzahlung (nicht) die Einzelrechnungen vorschlägt';

    public function handle(): int
    {
        $betrag = round((float) str_replace(',', '.', (string) $this->argument('betrag')), 2);

        // ABS() im SQL vermeiden (SQLite behandelt die Dezimalspalte als Text) –
        // stattdessen beide Vorzeichen mit Toleranz prüfen.
        $tx = BankTransaction::query()
            ->where(function ($q) use ($betrag) {
                $q->whereBetween('amount', [$betrag - 0.005, $betrag + 0.005])
                    ->orWhereBetween('amount', [-($betrag + 0.005), -($betrag - 0.005)]);
            })
            ->orderByDesc('id')->first();

        if (! $tx) {
            $this->error("Kein Umsatz mit Betrag {$betrag} gefunden.");

            return self::FAILURE;
        }

        $this->info('== Umsatz ==');
        $this->line("ID {$tx->id} · Betrag {$tx->amount} · Betrieb-ID " . ($tx->business_id ?: '—'));
        $this->line('Empfänger: ' . ($tx->counterparty ?: '—'));
        $this->line('Verwendungszweck: ' . mb_substr((string) $tx->purpose, 0, 200));

        $norm = fn ($s) => mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $s) ?? '');
        $txText = $norm($tx->purpose . ' ' . $tx->bank_reference);

        $this->newLine();
        $this->info('== Offene Belege (nicht zugeordnet, keine Dublette) ==');
        $receipts = Receipt::query()->unallocated()->notDuplicate()->get();
        $this->line($receipts->count() . ' Beleg(e) gesamt.');

        foreach ($receipts as $r) {
            $no = $norm($r->invoice_number);
            $imZweck = ($no !== '' && str_contains($txText, $no)) ? 'JA' : 'nein';
            $this->line(sprintf(
                '  #%d  Nr="%s"  Betrag=%s  Betrieb=%s  OCR=%s  Nr-im-Verwendungszweck=%s',
                $r->id,
                (string) $r->invoice_number,
                number_format((float) $r->gross_amount, 2, ',', '.'),
                $r->business_id ?: '—',
                filled($r->ocr_text) ? 'ja(' . mb_strlen((string) $r->ocr_text) . ')' : 'NEIN',
                $imZweck
            ));
        }

        $this->newLine();
        $this->info('== Ergebnis der Sammel-Erkennung ==');
        $result = (new MatchingEngine())->suggestFromAdvice($tx);
        if ($result) {
            $this->line('✓ Erkannt: ' . $result['invoices']->count() . ' Rechnungen, Summe '
                . number_format($result['sum'], 2, ',', '.') . ' €'
                . ($result['advice'] ? ' (Quelle: Avis #' . $result['advice']->id . ')' : ' (Quelle: Verwendungszweck)'));
            foreach ($result['invoices'] as $r) {
                $this->line('   - #' . $r->id . '  ' . $r->invoice_number . '  ' . number_format((float) $r->gross_amount, 2, ',', '.') . ' €');
            }
        } else {
            $this->warn('✗ Keine Sammelzahlung erkannt.');
            $this->line('Mögliche Gründe (siehe Liste oben):');
            $this->line('  - Weniger als 2 Belege, deren Nummer im Verwendungszweck/Avis steht.');
            $this->line('  - Die Summe der gefundenen Belege trifft den Umsatzbetrag nicht (±0,02).');
            $this->line('  - Belege gehören zu einem anderen Betrieb als der Umsatz.');
            $this->line('  - Rechnungsnummer wurde nicht erkannt (Nr="" oben).');
        }

        return self::SUCCESS;
    }
}
