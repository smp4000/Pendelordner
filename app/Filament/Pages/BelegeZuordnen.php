<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Receipt;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\OcrService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

/**
 * Beleg-Upload mit automatischer Zuordnungssuche (Modul 3/4).
 *
 * Mehrere Belege hochladen -> OCR -> die Matching-Engine sucht zu jedem (noch
 * nicht zugeordneten) Beleg den passenden Bankumsatz und schlägt ihn vor.
 * Ein Klick ordnet den Beleg dem vorgeschlagenen Umsatz zu.
 */
class BelegeZuordnen extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.belege-zuordnen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Belege';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Belege hochladen & zuordnen';

    protected static ?string $navigationLabel = 'Belege hochladen & zuordnen';

    /** @var array<int, mixed> */
    public array $uploadFiles = [];

    public string $uploadType = 'incoming_invoice';

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** Noch nicht zugeordnete Belege (neueste zuerst). */
    public function getUnassignedReceiptsProperty(): Collection
    {
        return Receipt::query()
            ->with('supplier')
            ->unallocated()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** Bester Umsatzvorschlag je Beleg (für die Anzeige). */
    public function suggestionFor(Receipt $receipt): ?array
    {
        return (new MatchingEngine())->suggestTransactions($receipt, 1)->first();
    }

    /** Mehrere Belege hochladen, OCR ausführen. */
    public function uploadReceipts(): void
    {
        $this->validate([
            'uploadFiles' => 'required|array',
            'uploadFiles.*' => 'file|max:20480',
        ], [], ['uploadFiles' => 'Dateien']);

        $diskName = config('pendelordner.belege_disk', 'belege');
        $count = 0;

        foreach ($this->uploadFiles as $file) {
            try {
                $path = $file->store(date('Y/m'), $diskName);

                $receipt = Receipt::create([
                    'type' => $this->uploadType,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'status' => 'new',
                ]);

                (new OcrService())->process($receipt->refresh());
                $count++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->reset('uploadFiles');

        Notification::make()
            ->title($count . ' Beleg(e) hochgeladen')
            ->body('OCR ausgeführt. Vorschläge zur Zuordnung werden unten angezeigt.')
            ->success()->send();
    }

    /** Beleg dem (vorgeschlagenen) Umsatz zuordnen. */
    public function assign(int $receiptId, int $transactionId): void
    {
        $receipt = Receipt::find($receiptId);
        $transaction = BankTransaction::find($transactionId);
        if (! $receipt || ! $transaction) {
            return;
        }

        $amount = $receipt->gross_amount > 0
            ? min((float) $receipt->gross_amount, abs((float) $transaction->amount))
            : abs((float) $transaction->amount);

        $transaction->receipts()->syncWithoutDetaching([
            $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'confirmed'],
        ]);
        $transaction->recalculateStatus();

        Notification::make()->title('Beleg zugeordnet')->success()->send();
    }
}
