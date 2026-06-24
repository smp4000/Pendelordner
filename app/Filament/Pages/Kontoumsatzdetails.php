<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Kontoumsatzdetails (Modul 6) – 3-Spalten-Ansicht im Lexware-Stil:
 *   links   = offene Bankumsätze
 *   mitte   = Details + zugeordnete Belege + Zuordnungsvorschläge
 *   rechts  = Vorschau des gewählten Belegs (PDF/Bild)
 */
class Kontoumsatzdetails extends Page
{
    protected string $view = 'filament.pages.kontoumsatzdetails';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Kontoumsatzdetails';

    protected static ?string $navigationLabel = 'Kontoumsatzdetails';

    public ?int $selectedTransactionId = null;

    public ?int $selectedReceiptId = null;

    public function mount(): void
    {
        $this->selectedTransactionId = BankTransaction::query()
            ->expense()
            ->open()
            ->orderBy('booking_date')
            ->value('id');
    }

    /** Linke Spalte: noch zu bearbeitende Umsätze. */
    public function getOpenTransactionsProperty(): Collection
    {
        return BankTransaction::query()
            ->with(['receipts'])
            ->open()
            ->orderBy('booking_date')
            ->limit(100)
            ->get();
    }

    public function getSelectedTransactionProperty(): ?BankTransaction
    {
        if (! $this->selectedTransactionId) {
            return null;
        }

        return BankTransaction::with(['receipts', 'category', 'costCenter', 'supplier', 'bankAccount'])
            ->find($this->selectedTransactionId);
    }

    public function getSelectedReceiptProperty(): ?Receipt
    {
        return $this->selectedReceiptId ? Receipt::find($this->selectedReceiptId) : null;
    }

    /** Zuordnungsvorschläge der Matching-Engine für den aktuellen Umsatz. */
    public function getSuggestionsProperty(): Collection
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return collect();
        }

        return (new MatchingEngine())->suggestReceipts($transaction);
    }

    public function selectTransaction(int $id): void
    {
        $this->selectedTransactionId = $id;
        $this->selectedReceiptId = $this->selectedTransaction?->receipts->first()?->id;
    }

    public function selectReceipt(int $id): void
    {
        $this->selectedReceiptId = $id;
    }

    /** Beleg dem Umsatz zuordnen (Restbetrag) und Status neu berechnen. */
    public function attachReceipt(int $receiptId): void
    {
        $transaction = $this->selectedTransaction;
        $receipt = Receipt::find($receiptId);
        if (! $transaction || ! $receipt) {
            return;
        }

        $amount = min(
            $receipt->open_amount > 0 ? $receipt->open_amount : (float) $receipt->gross_amount,
            $transaction->difference > 0 ? $transaction->difference : abs((float) $transaction->amount)
        );

        $transaction->receipts()->syncWithoutDetaching([
            $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'confirmed'],
        ]);
        $transaction->recalculateStatus();

        $this->selectedReceiptId = $receipt->id;

        Notification::make()->title('Beleg zugeordnet')->success()->send();
    }

    public function detachReceipt(int $receiptId): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $transaction->receipts()->detach($receiptId);
        $transaction->recalculateStatus();

        Notification::make()->title('Zuordnung gelöst')->success()->send();
    }

    /** Umsatz als geprüft markieren. */
    public function markReviewed(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $transaction->reviewed = true;
        $transaction->recalculateStatus();

        Notification::make()->title('Umsatz als geprüft markiert')->success()->send();
    }
}
