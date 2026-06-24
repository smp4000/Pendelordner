<?php

namespace App\Filament\Resources\Receipts\Pages;

use App\Filament\Resources\Receipts\ReceiptResource;
use App\Services\Ocr\OcrService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateReceipt extends CreateRecord
{
    protected static string $resource = ReceiptResource::class;

    /**
     * Nach dem Anlegen: Datei-Metadaten (MIME/Größe/Name) ergänzen und OCR
     * ausführen, sofern eine Datei hochgeladen wurde.
     */
    protected function afterCreate(): void
    {
        $receipt = $this->record;

        if (! $receipt->file_path) {
            return;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        if ($disk->exists($receipt->file_path)) {
            $receipt->forceFill([
                'file_name' => basename($receipt->file_path),
                'mime_type' => $disk->mimeType($receipt->file_path) ?: null,
                'file_size' => $disk->size($receipt->file_path),
            ])->saveQuietly();
        }

        // OCR läuft synchron (lokaler Einzelplatzbetrieb). Bei Server-Umzug
        // kann dies in einen Queue-Job ausgelagert werden.
        (new OcrService())->process($receipt->refresh());
    }
}
