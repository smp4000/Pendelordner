<?php

namespace App\Services\Bank;

use App\Enums\ImportSource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\ImportLog;
use App\Services\Matching\MatchingEngine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importiert normalisierte Bankumsätze (aus Datei-Parsern oder FinTS) in die
 * Datenbank (Modul 1). Übernimmt die Dublettenprüfung über den dedup_hash und
 * wendet optional die Zuordnungsregeln zur Vorkontierung an. Schreibt ein
 * Import-Protokoll mit Statistik.
 *
 * Erwartete Felder je Umsatz (array):
 *   booking_date (Pflicht, Y-m-d|Carbon), value_date, counterparty,
 *   counterparty_iban, counterparty_bic, purpose, amount (Pflicht, float),
 *   currency, bank_reference, transaction_code, booking_text, balance_after
 */
class BankImportService
{
    public function __construct(
        private readonly MatchingEngine $matching = new MatchingEngine(),
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     */
    public function import(
        BankAccount $account,
        array $transactions,
        ImportSource $source,
        ?string $filename = null,
        bool $applyRules = true,
    ): ImportLog {
        $log = ImportLog::create([
            'bank_account_id' => $account->id,
            'source' => $source->value,
            'filename' => $filename,
            'total_count' => count($transactions),
            'status' => 'running',
            'started_at' => now(),
        ]);

        $new = $duplicates = $restored = $errors = 0;

        foreach ($transactions as $row) {
            try {
                $data = $this->normalize($row, $account, $source);
                $hash = BankTransaction::makeDedupHash($data);

                // Dublettenprüfung inkl. gelöschter Umsätze – der Unique-Index
                // berücksichtigt Soft-Deletes nicht, deshalb withTrashed().
                $existing = BankTransaction::withTrashed()
                    ->where('bank_account_id', $account->id)
                    ->where('dedup_hash', $hash)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                        $restored++;
                    } else {
                        $duplicates++;
                    }

                    // Beschreibende Bankfelder auffrischen (z. B. korrigierter
                    // Verwendungszweck nach Parser-Verbesserung). Manuelle
                    // Zuordnungen (Kategorie/Kostenstelle/Konto/Mitteilung/
                    // Status) bleiben unberührt.
                    $existing->fill([
                        'purpose' => $data['purpose'],
                        'counterparty' => $data['counterparty'],
                        'booking_text' => $data['booking_text'],
                        'counterparty_iban' => $data['counterparty_iban'],
                        'counterparty_bic' => $data['counterparty_bic'],
                    ])->saveQuietly();

                    continue;
                }

                $transaction = new BankTransaction(array_merge($data, [
                    'bank_account_id' => $account->id,
                    'business_id' => $account->business_id,
                    'import_log_id' => $log->id,
                    'import_source' => $source->value,
                    'dedup_hash' => $hash,
                ]));
                $transaction->save();

                if ($applyRules) {
                    $this->matching->applyRules($transaction);
                }

                $new++;
            } catch (Throwable $e) {
                $errors++;
                report($e);
            }
        }

        // Wiederhergestellte (zuvor gelöschte) Umsätze zählen wie neue aktive
        // Buchungen für die Erfolgsbewertung.
        $effectiveNew = $new + $restored;

        $log->update([
            'new_count' => $effectiveNew,
            'duplicate_count' => $duplicates,
            'error_count' => $errors,
            'status' => $errors === 0 ? 'success' : ($effectiveNew > 0 ? 'partial' : 'error'),
            'message' => $restored > 0
                ? sprintf('%d neu, %d wiederhergestellt, %d Dubletten, %d Fehler.', $new, $restored, $duplicates, $errors)
                : sprintf('%d neu, %d Dubletten, %d Fehler.', $new, $duplicates, $errors),
            'finished_at' => now(),
        ]);

        // Saldo/letzten Abruf am Konto aktualisieren
        $account->forceFill(['last_fetched_at' => now()])->saveQuietly();

        return $log->refresh();
    }

    /**
     * Vereinheitlicht ein Roh-Array zu den Spalten von bank_transactions.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row, BankAccount $account, ImportSource $source): array
    {
        $bookingDate = $this->toDate($row['booking_date'] ?? null);
        if (! $bookingDate) {
            throw new \InvalidArgumentException('Bankumsatz ohne Buchungsdatum.');
        }

        return [
            'booking_date' => $bookingDate,
            'value_date' => $this->toDate($row['value_date'] ?? null),
            'counterparty' => $this->clean($row['counterparty'] ?? null),
            'counterparty_iban' => $this->clean($row['counterparty_iban'] ?? null),
            'counterparty_bic' => $this->clean($row['counterparty_bic'] ?? null),
            'purpose' => $this->clean($row['purpose'] ?? null),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
            'currency' => $this->clean($row['currency'] ?? null) ?: ($account->currency ?: 'EUR'),
            'bank_reference' => $this->clean($row['bank_reference'] ?? null),
            'transaction_code' => $this->clean($row['transaction_code'] ?? null),
            'booking_text' => $this->clean($row['booking_text'] ?? null),
            'balance_after' => isset($row['balance_after']) ? round((float) $row['balance_after'], 2) : null,
        ];
    }

    private function toDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
