<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\OcrService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Kontoumsatzdetails (Modul 6) – 3-Spalten-Ansicht im DATEV-/Lexware-Stil:
 *   links  = offene Bankumsätze
 *   mitte  = Tabs: Zugeordnete Belege · Vorschläge · Belegsuche · Hochladen
 *   rechts = Vorschau des gewählten Belegs (PDF/Bild)
 */
class Kontoumsatzdetails extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.kontoumsatzdetails';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Bank';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Kontoumsatzdetails';

    protected static ?string $navigationLabel = 'Kontoumsatzdetails';

    public ?int $selectedTransactionId = null;

    public ?int $selectedReceiptId = null;

    /** Aktiver Tab in der Mitte: assigned | suggestions | search | upload */
    public string $activeTab = 'assigned';

    // --- Manuelle Belegsuche -------------------------------------------------
    public string $searchQuery = '';

    public string $searchAssigned = 'unassigned'; // unassigned | assigned | all

    public string $searchPaid = 'all';            // all | paid | unpaid

    public string $searchType = 'all';            // all | <ReceiptType>

    // --- Upload --------------------------------------------------------------
    public $uploadFile = null;

    public string $uploadType = 'incoming_invoice';

    public function mount(): void
    {
        $this->selectedTransactionId = BankTransaction::query()
            ->expense()
            ->open()
            ->orderBy('booking_date')
            ->value('id');
    }

    // --- Daten ---------------------------------------------------------------

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

    public function getSuggestionsProperty(): Collection
    {
        $transaction = $this->selectedTransaction;

        return $transaction ? (new MatchingEngine())->suggestReceipts($transaction, 20) : collect();
    }

    /** Ergebnisse der manuellen Belegsuche. */
    public function getSearchResultsProperty(): Collection
    {
        return Receipt::query()
            ->with('supplier')
            ->when($this->searchAssigned === 'unassigned', fn ($q) => $q->whereDoesntHave('bankTransactions'))
            ->when($this->searchAssigned === 'assigned', fn ($q) => $q->whereHas('bankTransactions'))
            ->when($this->searchPaid === 'paid', fn ($q) => $q->where('paid', true))
            ->when($this->searchPaid === 'unpaid', fn ($q) => $q->where('paid', false))
            ->when($this->searchType !== 'all', fn ($q) => $q->where('type', $this->searchType))
            ->when($this->searchQuery !== '', function ($q) {
                $s = '%' . $this->searchQuery . '%';
                $q->where(function ($q) use ($s) {
                    $q->where('invoice_number', 'like', $s)
                        ->orWhere('ocr_text', 'like', $s)
                        ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', $s));
                });
            })
            ->orderByDesc('invoice_date')
            ->limit(50)
            ->get();
    }

    // --- Aktionen ------------------------------------------------------------

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function selectTransaction(int $id): void
    {
        $this->selectedTransactionId = $id;
        $this->selectedReceiptId = $this->selectedTransaction?->receipts->first()?->id;
        $this->activeTab = 'assigned';
    }

    public function selectReceipt(int $id): void
    {
        $this->selectedReceiptId = $id;
    }

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
        if ($amount <= 0) {
            $amount = abs((float) $transaction->amount);
        }

        $transaction->receipts()->syncWithoutDetaching([
            $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'confirmed'],
        ]);
        $transaction->recalculateStatus();

        $this->selectedReceiptId = $receipt->id;
        $this->activeTab = 'assigned';

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

    public function togglePaid(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }
        $transaction->fully_paid = ! $transaction->fully_paid;
        $transaction->saveQuietly();
    }

    /** Beleg hochladen (Upload online), OCR ausführen und dem Umsatz zuordnen. */
    public function uploadReceipt(): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            Notification::make()->title('Kein Umsatz gewählt')->warning()->send();

            return;
        }

        $this->validate([
            'uploadFile' => 'required|file|max:20480', // max. 20 MB
        ], [], ['uploadFile' => 'Datei']);

        try {
            $diskName = config('pendelordner.belege_disk', 'belege');
            $path = $this->uploadFile->store(date('Y/m'), $diskName);

            $receipt = Receipt::create([
                'type' => $this->uploadType,
                'business_id' => $transaction->business_id,
                'file_path' => $path,
                'file_name' => $this->uploadFile->getClientOriginalName(),
                'mime_type' => $this->uploadFile->getMimeType(),
                'file_size' => $this->uploadFile->getSize(),
                'status' => 'new',
            ]);

            // OCR ausführen (füllt Rechnungsnummer, Datum, Beträge …)
            (new OcrService())->process($receipt->refresh());
            $receipt->refresh();

            // Betrag zuordnen
            $amount = $receipt->gross_amount > 0
                ? min((float) $receipt->gross_amount, abs((float) $transaction->amount))
                : ($transaction->difference > 0 ? $transaction->difference : abs((float) $transaction->amount));

            $transaction->receipts()->syncWithoutDetaching([
                $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'manual'],
            ]);
            $transaction->recalculateStatus();

            $this->reset('uploadFile');
            $this->selectedReceiptId = $receipt->id;
            $this->activeTab = 'assigned';

            Notification::make()->title('Beleg hochgeladen & zugeordnet')->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Upload fehlgeschlagen')->body($e->getMessage())->danger()->send();
        }
    }
}
