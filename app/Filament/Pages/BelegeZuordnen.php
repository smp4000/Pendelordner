<?php

namespace App\Filament\Pages;

use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\Receipt;
use App\Models\Supplier;
use App\Services\Matching\MatchingEngine;
use App\Services\Ocr\OcrService;
use App\Services\Ocr\ReceiptParser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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
class BelegeZuordnen extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
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
            ->with(['supplier', 'business'])
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
        $skipped = 0;

        foreach ($this->uploadFiles as $file) {
            try {
                // Dublettenprüfung über den Datei-Hash.
                $hash = hash('sha256', $file->get());
                if (Receipt::withTrashed()->where('file_hash', $hash)->exists()) {
                    $skipped++;

                    continue;
                }

                $path = $file->store(date('Y/m'), $diskName);

                $receipt = Receipt::create([
                    'type' => $this->uploadType,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'file_hash' => $hash,
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
            ->body('OCR ausgeführt.' . ($skipped > 0 ? ' ' . $skipped . ' Dublette(n) übersprungen.' : '')
                . ' Vorschläge zur Zuordnung werden unten angezeigt.')
            ->success()->send();
    }

    /**
     * Modal zum Anlegen eines neuen Lieferanten – vorbefüllt aus den
     * OCR-Daten des Belegs (Name, USt-IdNr, IBAN, Kundennummer). Nach dem
     * Speichern wird der Lieferant dem Beleg zugeordnet.
     */
    public function createSupplierAction(): Action
    {
        return Action::make('createSupplier')
            ->label('Lieferant anlegen')
            ->icon('heroicon-o-user-plus')
            ->modalHeading('Neuen Lieferanten anlegen')
            ->modalSubmitActionLabel('Anlegen & zuordnen')
            ->fillForm(function (array $arguments): array {
                $receipt = Receipt::find($arguments['receipt'] ?? null);
                $parser = new ReceiptParser();
                $text = (string) ($receipt?->ocr_text ?? '');

                return [
                    'name' => $parser->supplierNameGuess($text),
                    'vat_id' => $parser->vatId($text),
                    'iban' => $receipt?->iban,
                    'business_id' => $receipt?->business_id,
                    'customer_number' => $receipt?->customer_number,
                ];
            })
            ->schema([
                TextInput::make('name')->label('Name')->required(),
                TextInput::make('vat_id')->label('USt-IdNr.'),
                TextInput::make('iban')->label('IBAN'),
                Select::make('business_id')->label('Tankstelle')
                    ->options(Business::orderBy('sort_order')->get()->pluck('display_label', 'id'))
                    ->helperText('Für die Kundennummer-Verknüpfung.'),
                TextInput::make('customer_number')->label('Kundennummer'),
            ])
            ->action(function (array $data, array $arguments): void {
                $receipt = Receipt::find($arguments['receipt'] ?? null);
                if (! $receipt) {
                    return;
                }

                $supplier = Supplier::create([
                    'name' => $data['name'],
                    'display_name' => $data['name'],
                    'vat_id' => $data['vat_id'] ?: null,
                    'iban' => $data['iban'] ?: null,
                    'active' => true,
                ]);

                // Kundennummer je Tankstelle verknüpfen (für künftige Auto-Zuordnung).
                if (! empty($data['business_id'])) {
                    $supplier->customerNumbers()->create([
                        'business_id' => $data['business_id'],
                        'customer_number' => $data['customer_number'] ?: null,
                    ]);
                }

                $receipt->supplier_id = $supplier->id;
                if (! empty($data['business_id']) && blank($receipt->business_id)) {
                    $receipt->business_id = $data['business_id'];
                }
                $receipt->saveQuietly();

                Notification::make()
                    ->title('Lieferant „' . $supplier->name . '" angelegt und zugeordnet')
                    ->success()->send();
            });
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
            $receipt->id => ['amount' => round($amount, 2), 'match_type' => 'confirmed', 'sort_order' => $transaction->receipts()->count()],
        ]);
        $transaction->recalculateStatus();

        Notification::make()->title('Beleg zugeordnet')->success()->send();
    }
}
