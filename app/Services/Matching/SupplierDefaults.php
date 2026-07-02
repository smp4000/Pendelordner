<?php

namespace App\Services\Matching;

use App\Models\BankTransaction;
use App\Models\LedgerAccount;
use App\Models\Receipt;
use App\Models\SupplierCustomerNumber;

/**
 * Wendet die Standard-Zuordnung des Lieferanten auf einen Bankumsatz an,
 * sobald ein Beleg zugeordnet wird – tankstellen-/kundennummernabhängig:
 *
 *   1. Verknüpfung Lieferant + Kundennummer (bzw. + Tankstelle) liefert
 *      Kostenstelle und eDTAS-Konto dieser Tankstelle.
 *   2. Fallback: die globalen Lieferanten-Defaults (Kategorie, Kostenstelle,
 *      eDTAS-Konto).
 *
 * Es werden nur LEERE Felder des Umsatzes befüllt – manuelle Zuordnungen
 * bleiben unangetastet.
 */
class SupplierDefaults
{
    /** Vorbelegung aus Beleg/Lieferant auf den Umsatz anwenden (nur leere Felder). */
    public function applyToTransaction(BankTransaction $transaction, Receipt $receipt): void
    {
        $supplier = $receipt->supplier;
        if (! $supplier) {
            return;
        }

        $dirty = false;

        if (blank($transaction->supplier_id)) {
            $transaction->supplier_id = $supplier->id;
            $dirty = true;
        }

        // Passende Kundennummern-Verknüpfung: zuerst über die Kundennummer des
        // Belegs, sonst über die Tankstelle des Belegs/Umsatzes.
        $link = $this->resolveLink($receipt, $transaction);

        if (blank($transaction->cost_center_id)) {
            $costCenterId = $link?->cost_center_id ?: $supplier->default_cost_center_id;
            if ($costCenterId) {
                $transaction->cost_center_id = $costCenterId;
                $dirty = true;
            }
        }

        if (blank($transaction->category_id) && $supplier->default_category_id) {
            $transaction->category_id = $supplier->default_category_id;
            $dirty = true;
        }

        if (blank($transaction->ledger_account_id)) {
            $number = $link?->edtas_account ?: $supplier->edtas_account;
            if ($number) {
                $ledger = LedgerAccount::whereIn('chart', ['edtas', 'kfz', 'gastro'])
                    ->where('number', $number)->first();
                if ($ledger) {
                    $transaction->ledger_account_id = $ledger->id;
                    $dirty = true;
                }
            }
        }

        if ($dirty) {
            $transaction->saveQuietly();
        }
    }

    private function resolveLink(Receipt $receipt, BankTransaction $transaction): ?SupplierCustomerNumber
    {
        $query = SupplierCustomerNumber::where('supplier_id', $receipt->supplier_id);

        if (filled($receipt->customer_number)) {
            $byNumber = (clone $query)->where('customer_number', $receipt->customer_number)->get();
            if ($byNumber->count() === 1) {
                return $byNumber->first();
            }
        }

        $businessId = $receipt->business_id ?: $transaction->business_id;
        if ($businessId) {
            $byBusiness = (clone $query)->where('business_id', $businessId)->get();
            if ($byBusiness->count() === 1) {
                return $byBusiness->first();
            }
        }

        return null;
    }
}
