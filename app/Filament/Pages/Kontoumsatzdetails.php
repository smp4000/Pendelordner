<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\CostCenter;
use App\Models\LedgerAccount;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\OcrService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
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

    // Filterkontext aus der Umsatzliste (beschränkt die Navigation).
    public ?int $filterAccountId = null;

    public ?string $filterFrom = null;

    public ?string $filterTo = null;

    public ?string $filterStatus = null;

    public ?string $filterReviewed = null;

    public ?string $filterWithoutReceipt = null;

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

    // --- Inline-Zuordnung (Kategorie / Kostenstelle / Sachkonto) -------------
    public ?int $assignCategoryId = null;

    public ?int $assignCostCenterId = null;

    public ?int $assignLedgerAccountId = null;

    public string $ledgerSearch = '';

    // Neue Kategorie inline anlegen
    public bool $showNewCategory = false;

    public string $newCategory = '';

    // --- Mitteilung an den Steuerberater -------------------------------------
    public bool $showNote = false;

    public string $accountantNote = '';

    public function mount(): void
    {
        // Filterkontext aus der Umsatzliste übernehmen (Query-Parameter).
        $this->filterAccountId = request('account_id') ? (int) request('account_id') : null;
        $this->filterFrom = request('from') ?: null;
        $this->filterTo = request('to') ?: null;
        $this->filterStatus = request('status') ?: null;
        $this->filterReviewed = request()->has('reviewed') ? (string) request('reviewed') : null;
        $this->filterWithoutReceipt = request()->has('without_receipt') ? (string) request('without_receipt') : null;

        $this->selectedTransactionId = request('tx')
            ? (int) request('tx')
            : $this->navigationQuery()->value('id');

        $this->fillAssign();
    }

    /** Zuordnungsfelder aus dem aktuellen Umsatz vorbelegen. */
    private function fillAssign(): void
    {
        $t = $this->selectedTransaction;
        $this->assignCategoryId = $t?->category_id;
        $this->assignCostCenterId = $t?->cost_center_id;
        $this->assignLedgerAccountId = $t?->ledger_account_id;
        $this->ledgerSearch = '';
        $this->accountantNote = (string) ($t?->accountant_note ?? '');
        $this->showNote = $this->accountantNote !== '';
    }

    public function toggleNote(): void
    {
        $this->showNote = ! $this->showNote;
    }

    /** Mitteilung an den Steuerberater am aktuellen Umsatz speichern. */
    public function saveNote(): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }

        $note = trim($this->accountantNote);
        BankTransaction::whereKey($this->selectedTransactionId)
            ->update(['accountant_note' => $note !== '' ? $note : null]);

        $this->accountantNote = $note;
        $this->showNote = $note !== '';

        Notification::make()
            ->title($note !== '' ? 'Mitteilung gespeichert' : 'Mitteilung entfernt')
            ->success()->send();
    }

    /** Speichert ein einzelnes Zuordnungsfeld am aktuellen Umsatz. */
    private function saveAssign(string $field, $value): void
    {
        if (! $this->selectedTransactionId) {
            return;
        }
        BankTransaction::whereKey($this->selectedTransactionId)->update([$field => $value ?: null]);

        Notification::make()->title('Zuordnung gespeichert')->success()->send();
    }

    public function updatedAssignCategoryId($value): void
    {
        $this->saveAssign('category_id', $value);
    }

    public function updatedAssignCostCenterId($value): void
    {
        $this->saveAssign('cost_center_id', $value);
    }

    public function toggleNewCategory(): void
    {
        $this->showNewCategory = ! $this->showNewCategory;
        $this->newCategory = '';
    }

    /** Neue Kategorie anlegen, dem Umsatz zuordnen und auswählen. */
    public function createCategory(): void
    {
        $name = trim($this->newCategory);
        if ($name === '') {
            return;
        }

        $category = Category::firstOrCreate(['name' => $name], ['active' => true]);

        $this->assignCategoryId = $category->id;
        $this->saveAssign('category_id', $category->id);

        $this->newCategory = '';
        $this->showNewCategory = false;

        Notification::make()->title('Kategorie „' . $category->name . '" angelegt')->success()->send();
    }

    /** @return Collection<int, Category> */
    public function getCategoriesProperty(): Collection
    {
        return Category::where('active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, CostCenter> */
    public function getCostCentersProperty(): Collection
    {
        return CostCenter::where('active', true)->orderBy('name')->get();
    }

    /** Treffer für die Sachkonto-Suche (Nummer oder Bezeichnung). */
    public function getLedgerResultsProperty(): Collection
    {
        $s = trim($this->ledgerSearch);
        if (mb_strlen($s) < 2) {
            return collect();
        }

        return LedgerAccount::query()
            ->where(fn ($q) => $q->where('number', 'like', $s . '%')->orWhere('name', 'like', '%' . $s . '%'))
            ->orderBy('number')
            ->limit(15)
            ->get();
    }

    public function getCurrentLedgerProperty(): ?LedgerAccount
    {
        return $this->assignLedgerAccountId ? LedgerAccount::find($this->assignLedgerAccountId) : null;
    }

    public function setLedger(int $id): void
    {
        $this->assignLedgerAccountId = $id;
        $this->ledgerSearch = '';
        $this->saveAssign('ledger_account_id', $id);
    }

    public function clearLedger(): void
    {
        $this->assignLedgerAccountId = null;
        $this->saveAssign('ledger_account_id', null);
    }

    /** Basisquery der Navigation – gefiltert (aus der Liste) oder Standard (offene Ausgaben). */
    private function navigationQuery()
    {
        if ($this->hasFilterContext()) {
            return BankTransaction::query()
                ->when($this->filterAccountId, fn ($q) => $q->where('bank_account_id', $this->filterAccountId))
                ->when($this->filterFrom, fn ($q) => $q->whereDate('booking_date', '>=', $this->filterFrom))
                ->when($this->filterTo, fn ($q) => $q->whereDate('booking_date', '<=', $this->filterTo))
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterReviewed !== null, fn ($q) => $q->where('reviewed', $this->filterReviewed === '1'))
                ->when($this->filterWithoutReceipt !== null, fn ($q) => $this->filterWithoutReceipt === '1'
                    ? $q->whereDoesntHave('receipts')
                    : $q->whereHas('receipts'))
                ->orderBy('booking_date');
        }

        return BankTransaction::query()->expense()->open()->orderBy('booking_date');
    }

    private function hasFilterContext(): bool
    {
        return $this->filterAccountId || $this->filterFrom || $this->filterTo
            || $this->filterStatus || $this->filterReviewed !== null || $this->filterWithoutReceipt !== null;
    }

    /** Seite über die volle Panelbreite anzeigen. */
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    // --- Navigation (Umsatz X von Y, vor/zurück) ----------------------------

    public function getPositionProperty(): int
    {
        $ids = $this->openTransactions->pluck('id')->all();
        $i = array_search($this->selectedTransactionId, $ids, true);

        return $i === false ? 0 : $i + 1;
    }

    public function getTotalProperty(): int
    {
        return $this->openTransactions->count();
    }

    public function goTo(string $where): void
    {
        $ids = $this->openTransactions->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        $i = array_search($this->selectedTransactionId, $ids, true);
        $i = $i === false ? 0 : $i;

        $target = match ($where) {
            'first' => $ids[0],
            'last' => $ids[count($ids) - 1],
            'prev' => $ids[max(0, $i - 1)],
            'next' => $ids[min(count($ids) - 1, $i + 1)],
            default => $ids[$i],
        };

        $this->selectTransaction($target);
    }

    // --- Daten ---------------------------------------------------------------

    public function getOpenTransactionsProperty(): Collection
    {
        $list = $this->navigationQuery()
            ->with(['receipts'])
            ->limit(500)
            ->get();

        // Den aktuell gewählten Umsatz sicher enthalten (z. B. wenn er nach
        // "geprüft" aus der Standardliste fiele), damit die Position stimmt.
        if ($this->selectedTransactionId && ! $list->contains('id', $this->selectedTransactionId)) {
            if ($sel = BankTransaction::with('receipts')->find($this->selectedTransactionId)) {
                $list = $list->push($sel)->sortBy('booking_date')->values();
            }
        }

        return $list;
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
        if ($this->selectedReceiptId) {
            if ($r = Receipt::find($this->selectedReceiptId)) {
                return $r;
            }
        }

        // Fällt automatisch auf den ersten zugeordneten Beleg zurück, damit die
        // Vorschau auch ohne Klick erscheint.
        return $this->selectedTransaction?->receipts->first();
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
        $this->fillAssign();
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

    /** Zugeordneten (Teil-)Betrag eines Belegs ändern. */
    public function updateAllocation(int $receiptId, $amount): void
    {
        $transaction = $this->selectedTransaction;
        if (! $transaction) {
            return;
        }

        $value = round((float) str_replace(',', '.', (string) $amount), 2);

        $transaction->receipts()->updateExistingPivot($receiptId, ['amount' => $value]);
        $transaction->recalculateStatus();

        Notification::make()->title('Betrag aktualisiert')->success()->send();
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
