<?php

namespace App\Console\Commands;

use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ruft die Cloud-OCR (OCR.space) für EINEN Beleg direkt auf und zeigt die
 * vollständige Antwort – Fehlermeldung, ExitCode, Dateigröße, Seitenzahl.
 * So lässt sich feststellen, warum ein Beleg per Cloud-OCR keinen Text bekommt
 * (Tempo-/Kontingent-Limit, Datei zu groß, zu viele Seiten, Format …).
 */
class BelegCloudTest extends Command
{
    protected $signature = 'belege:cloud-test {id : Beleg-ID}';

    protected $description = 'Cloud-OCR (OCR.space) für einen Beleg testen und die Antwort anzeigen';

    public function handle(): int
    {
        $cfg = config('pendelordner.ocr.cloud', []);
        if (empty($cfg['enabled']) || empty($cfg['api_key'])) {
            $this->error('Cloud-OCR ist nicht aktiv (OCR_CLOUD_ENABLED / OCR_CLOUD_API_KEY prüfen).');

            return self::FAILURE;
        }

        $receipt = Receipt::find((int) $this->argument('id'));
        if (! $receipt || ! $receipt->file_path) {
            $this->error('Beleg nicht gefunden oder ohne Datei.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $abs = $disk->path($receipt->file_path);
        if (! is_file($abs)) {
            $this->error('Datei nicht vorhanden: ' . $receipt->file_path);

            return self::FAILURE;
        }

        $bytes = filesize($abs);
        $this->line('Datei: ' . $receipt->file_name . '  (' . number_format($bytes / 1024, 1, ',', '.') . ' KB)');
        $this->line('MIME:  ' . $receipt->mime_type);
        if ($bytes > (int) ($cfg['max_bytes'] ?? 0) && (int) ($cfg['max_bytes'] ?? 0) > 0) {
            $this->warn('Datei größer als OCR_CLOUD_MAX_BYTES (' . $cfg['max_bytes'] . ') – würde übersprungen.');
        }

        try {
            $response = Http::timeout((int) ($cfg['timeout'] ?? 60))
                ->attach('file', (string) file_get_contents($abs), basename($abs))
                ->post((string) ($cfg['endpoint'] ?? 'https://api.ocr.space/parse/image'), [
                    'apikey' => (string) $cfg['api_key'],
                    'language' => (string) ($cfg['language'] ?? 'ger'),
                    'isOverlayRequired' => 'false',
                    'scale' => 'true',
                    'OCREngine' => (string) ($cfg['engine'] ?? 2),
                    'filetype' => str_contains(strtolower((string) $receipt->mime_type), 'pdf') ? 'PDF' : 'Auto',
                ]);

            $this->line('HTTP-Status: ' . $response->status());
            $data = $response->json();

            $this->line('IsErroredOnProcessing: ' . var_export($data['IsErroredOnProcessing'] ?? null, true));
            $this->line('OCRExitCode: ' . var_export($data['OCRExitCode'] ?? null, true));
            if (! empty($data['ErrorMessage'])) {
                $this->error('ErrorMessage: ' . json_encode($data['ErrorMessage'], JSON_UNESCAPED_UNICODE));
            }
            if (! empty($data['ErrorDetails'])) {
                $this->error('ErrorDetails: ' . $data['ErrorDetails']);
            }

            $text = '';
            foreach (($data['ParsedResults'] ?? []) as $r) {
                $text .= (string) ($r['ParsedText'] ?? '');
            }
            $this->line('Erkannter Text: ' . mb_strlen(trim($text)) . ' Zeichen');
            if (trim($text) !== '') {
                $this->line('Auszug: ' . mb_substr(trim($text), 0, 200));
            }
        } catch (Throwable $e) {
            $this->error('Ausnahme: ' . $e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
