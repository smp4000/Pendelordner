<?php

namespace App\Console\Commands;

use App\Enums\ReceiptType;
use App\Models\Receipt;
use App\Services\Ocr\OcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Massen-Import von Belegen aus einem Ordner (Modul 3).
 *
 * Für viele Dateien auf einmal (z. B. mehrere hundert), die der Browser-Upload
 * nicht schafft (PHP max_file_uploads, Timeout). Die Dateien werden per FTP in
 * einen Ordner auf dem Server gelegt; dieser Befehl liest sie ein, prüft
 * Dubletten (Datei-Hash), legt je Datei einen Beleg an und führt die OCR aus –
 * schonend in Häppchen mit Pause (Cloud-OCR-Tempo-Limit).
 *
 * Beispiel:
 *   php artisan belege:ordner-import /www/htdocs/w01b773f/belege-eingang --pause=1500
 *   php artisan belege:ordner-import ./import --keine-ocr   (nur einlesen, OCR später)
 */
class BelegeOrdnerImport extends Command
{
    protected $signature = 'belege:ordner-import
        {pfad : Ordner mit den Belegdateien (wird rekursiv durchsucht)}
        {--limit=0 : Höchstzahl je Lauf (0 = alle)}
        {--pause=1500 : Pause in Millisekunden zwischen den Dateien (schont das Cloud-OCR-Limit)}
        {--keine-ocr : Nur einlesen, keine OCR (später mit belege:ocr-neu nachholen)}
        {--verschieben= : Fertige Dateien in diesen Unterordner verschieben (z. B. "_erledigt")}
        {--business= : Betriebs-ID, die den Belegen zugewiesen wird (optional)}';

    protected $description = 'Belege aus einem Serverordner massenhaft einlesen (Dublettenprüfung + OCR)';

    public function handle(): int
    {
        $pfad = (string) $this->argument('pfad');
        if (! is_dir($pfad)) {
            $this->error('Ordner nicht gefunden: ' . $pfad);

            return self::FAILURE;
        }

        $cfg = config('pendelordner.mail_ingest');
        $allowed = array_map('strtolower', $cfg['extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);
        // Anders als beim E-Mail-Import KEINE Mindestgröße: der Ordner wurde
        // bewusst befüllt, echte (auch kleine) Belege sollen nicht still
        // verworfen werden. Nur komplett leere Dateien werden übersprungen.

        $limit = (int) $this->option('limit');
        $pause = (int) $this->option('pause');
        $mitOcr = ! $this->option('keine-ocr');
        $verschieben = trim((string) $this->option('verschieben'));
        $businessId = $this->option('business') !== null ? (int) $this->option('business') : ($cfg['business_id'] ?: null);

        // Alle passenden Dateien rekursiv einsammeln (sortiert für stabile Reihenfolge).
        $dateien = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, $allowed, true)) {
                continue;
            }
            $dateien[] = $file->getPathname();
        }
        sort($dateien);

        if (empty($dateien)) {
            $this->warn('Keine passenden Dateien (' . implode(', ', $allowed) . ') im Ordner gefunden.');

            return self::SUCCESS;
        }

        $this->info(count($dateien) . ' Datei(en) gefunden. Import startet …');

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $service = new OcrService();
        $zielUnterordner = $verschieben !== '' ? rtrim($pfad, '/\\') . DIRECTORY_SEPARATOR . $verschieben : null;
        if ($zielUnterordner && ! is_dir($zielUnterordner)) {
            @mkdir($zielUnterordner, 0775, true);
        }

        $neu = 0;
        $dubletten = 0;
        $fehler = 0;
        $ocrText = 0;
        $verarbeitet = 0;

        foreach ($dateien as $absPfad) {
            if ($limit > 0 && $verarbeitet >= $limit) {
                break;
            }
            $verarbeitet++;

            try {
                $content = @file_get_contents($absPfad);
                if ($content === false || $content === '') {
                    $fehler++;
                    $this->line('  ! Leer/Lesefehler: ' . basename($absPfad));

                    continue;
                }

                $hash = hash('sha256', $content);
                if (Receipt::withTrashed()->where('file_hash', $hash)->exists()) {
                    $dubletten++;
                    $this->maybeMove($absPfad, $zielUnterordner);

                    continue;
                }

                $ext = strtolower(pathinfo($absPfad, PATHINFO_EXTENSION));
                $zielPfad = date('Y/m') . '/' . Str::random(40) . '.' . $ext;
                $disk->put($zielPfad, $content);

                $receipt = Receipt::create([
                    'type' => ReceiptType::IncomingInvoice,
                    'business_id' => $businessId,
                    'file_path' => $zielPfad,
                    'file_name' => basename($absPfad),
                    'mime_type' => $this->mimeAusEndung($ext),
                    'file_size' => strlen($content),
                    'file_hash' => $hash,
                    'status' => 'new',
                    'note' => 'Ordner-Import',
                ]);

                if ($mitOcr) {
                    try {
                        $service->process($receipt->refresh());
                        $receipt->refresh();
                        if (mb_strlen(trim((string) $receipt->ocr_text)) > 0) {
                            $ocrText++;
                        }
                    } catch (Throwable $e) {
                        report($e); // OCR-Fehler stoppt den Import nicht
                    }
                }

                $neu++;
                $this->maybeMove($absPfad, $zielUnterordner);

                if ($verarbeitet % 25 === 0) {
                    $this->line(sprintf('  … %d/%d verarbeitet (%d neu, %d Dublette)', $verarbeitet, count($dateien), $neu, $dubletten));
                }
            } catch (Throwable $e) {
                $fehler++;
                report($e);
                $this->line('  ! Fehler bei ' . basename($absPfad) . ': ' . $e->getMessage());
            }

            // Pause nur, wenn OCR läuft (sonst kein Cloud-Zugriff, keine Pause nötig).
            if ($mitOcr && $pause > 0) {
                usleep($pause * 1000);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Fertig: %d neu angelegt, %d Dublette(n) übersprungen, %d Fehler.',
            $neu, $dubletten, $fehler
        ));
        if ($mitOcr) {
            $this->line($ocrText . ' der neuen Belege haben nach OCR Text. Textlose später: php artisan belege:ocr-neu');
        } else {
            $this->line('OCR übersprungen. Jetzt nachholen mit: php artisan belege:ocr-neu --limit=' . max($neu, 200));
        }

        return self::SUCCESS;
    }

    /** Datei nach erfolgreicher Verarbeitung in den Zielordner verschieben (falls gewünscht). */
    private function maybeMove(string $absPfad, ?string $zielUnterordner): void
    {
        if (! $zielUnterordner) {
            return;
        }
        $ziel = $zielUnterordner . DIRECTORY_SEPARATOR . basename($absPfad);
        @rename($absPfad, $ziel);
    }

    private function mimeAusEndung(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'tif', 'tiff' => 'image/tiff',
            default => 'application/octet-stream',
        };
    }
}
