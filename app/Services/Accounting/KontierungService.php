<?php

namespace App\Services\Accounting;

use App\Enums\ChartOfAccounts;
use App\Models\AccountAssignment;
use App\Models\BankTransaction;
use Illuminate\Support\Str;

/**
 * Erzeugt Kontierungen (Buchungssätze) für Bankumsätze (Modul 13 – Vorbereitung
 * Buchhaltung). Leitet Konto/Gegenkonto/Steuerschlüssel aus Kategorie bzw.
 * Lieferant ab. Bucht je Umsatz genau eine Kontierung (wird bei erneutem Lauf
 * aktualisiert), solange sie noch nicht exportiert wurde.
 *
 * Konvention (DATEV):
 *   - Konto       = Aufwands-/Ertragskonto (aus Kategorie/Lieferant)
 *   - Gegenkonto  = Geldkonto (Bank) aus der Konfiguration
 *   - Betrag      = Bruttobetrag (absolut)
 */
class KontierungService
{
    /**
     * Kontiert einen Bankumsatz. Gibt die Kontierung zurück – oder null, wenn
     * kein Aufwands-/Ertragskonto ermittelbar war und kein Sammelkonto greift.
     */
    public function bookTransaction(BankTransaction $transaction, ?ChartOfAccounts $chart = null): ?AccountAssignment
    {
        $chart ??= ChartOfAccounts::from(config('pendelordner.kontierung.standard_kontenrahmen', 'edtas'));
        $chartKey = $chart->value;

        // Aufwands-/Ertragskonto (edtas): Kategorie hat Vorrang vor Lieferant.
        $account = $transaction->category?->edtas_account
            ?? $transaction->supplier?->edtas_account
            ?? config("pendelordner.kontierung.sammelkonto.{$chartKey}");

        if (! $account) {
            return null;
        }

        $bankAccount = config("pendelordner.kontierung.geldkonten.{$chartKey}.bank");
        $taxKey = $transaction->category?->tax_key ?? $transaction->supplier?->tax_key;

        // Bestehende, noch nicht exportierte Kontierung wiederverwenden.
        $assignment = $transaction->accountAssignments()
            ->where('exported', false)
            ->first() ?? new AccountAssignment(['assignable_id' => $transaction->id, 'assignable_type' => $transaction->getMorphClass()]);

        $assignment->fill([
            'chart_of_accounts' => $chartKey,
            'account' => $account,
            'contra_account' => $bankAccount,
            'tax_key' => $taxKey,
            'cost_center_id' => $transaction->cost_center_id,
            'document_number' => $transaction->bank_reference,
            'booking_text' => Str::limit((string) ($transaction->counterparty ?: $transaction->purpose), 60, ''),
            'service_date' => $transaction->value_date ?? $transaction->booking_date,
            'booking_date' => $transaction->booking_date,
            'amount' => abs((float) $transaction->amount),
        ]);

        $transaction->accountAssignments()->save($assignment);

        return $assignment;
    }

    /**
     * Kontiert mehrere Umsätze und gibt zurück, wie viele kontiert bzw.
     * übersprungen wurden.
     *
     * @param  iterable<BankTransaction>  $transactions
     * @return array{booked: int, skipped: int}
     */
    public function bookMany(iterable $transactions, ?ChartOfAccounts $chart = null): array
    {
        $booked = $skipped = 0;

        foreach ($transactions as $transaction) {
            $this->bookTransaction($transaction, $chart) ? $booked++ : $skipped++;
        }

        return ['booked' => $booked, 'skipped' => $skipped];
    }
}
