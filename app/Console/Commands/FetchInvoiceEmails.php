<?php

namespace App\Console\Commands;

use App\Enums\ReceiptType;
use App\Models\Receipt;
use App\Services\Ocr\OcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

/**
 * Rechnungseingang per E-Mail (Modul 3).
 *
 * Fragt das konfigurierte IMAP-Postfach ab, speichert PDF-/Bild-Anhänge
 * ungelesener Mails als Belege im Belegarchiv und startet die OCR-Auswertung.
 * Konfiguration unter config/pendelordner.php → mail_ingest (per .env).
 */
class FetchInvoiceEmails extends Command
{
    protected $signature = 'belege:fetch-mail {--limit=50 : Maximale Anzahl Mails pro Lauf}';

    protected $description = 'Holt Rechnungs-Anhänge aus dem IMAP-Postfach und legt sie als Belege an.';

    public function handle(): int
    {
        $cfg = config('pendelordner.mail_ingest');

        if (! ($cfg['enabled'] ?? false)) {
            $this->info('Mail-Eingang ist deaktiviert (MAIL_INGEST_ENABLED=false).');

            return self::SUCCESS;
        }

        foreach (['host', 'username', 'password'] as $req) {
            if (empty($cfg[$req])) {
                $this->error("Mail-Eingang: '$req' ist nicht konfiguriert (.env MAIL_INGEST_*).");

                return self::FAILURE;
            }
        }

        try {
            $client = (new ClientManager())->make([
                'host' => $cfg['host'],
                'port' => $cfg['port'],
                'encryption' => $cfg['encryption'] ?: false,
                'validate_cert' => $cfg['validate_cert'],
                'username' => $cfg['username'],
                'password' => $cfg['password'],
                'protocol' => 'imap',
            ]);
            $client->connect();
        } catch (Throwable $e) {
            $this->error('Verbindung zum Postfach fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $folder = $client->getFolder($cfg['folder'] ?: 'INBOX');
        if (! $folder) {
            $this->error('IMAP-Ordner nicht gefunden: ' . $cfg['folder']);

            return self::FAILURE;
        }

        $messages = $folder->query()->whereUnseen()->limit((int) $this->option('limit'))->get();
        $this->info($messages->count() . ' ungelesene Mail(s) gefunden.');

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $allowed = array_map('strtolower', $cfg['extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);
        $created = 0;

        foreach ($messages as $message) {
            try {
                foreach ($message->getAttachments() as $attachment) {
                    $name = (string) $attachment->getName();
                    $ext = strtolower((string) ($attachment->getExtension() ?: pathinfo($name, PATHINFO_EXTENSION)));

                    if (! in_array($ext, $allowed, true)) {
                        continue;
                    }

                    $content = $attachment->getContent();
                    if ($content === null || $content === '') {
                        continue;
                    }

                    // Dublettenprüfung über den Datei-Hash (gleiche Datei doppelt).
                    $hash = hash('sha256', $content);
                    if (Receipt::withTrashed()->where('file_hash', $hash)->exists()) {
                        $this->line('  = Dublette übersprungen: ' . $name);

                        continue;
                    }

                    $path = date('Y/m') . '/' . Str::random(40) . '.' . $ext;
                    $disk->put($path, $content);

                    $receipt = Receipt::create([
                        'type' => ReceiptType::IncomingInvoice,
                        'business_id' => $cfg['business_id'] ?: null,
                        'file_path' => $path,
                        'file_name' => $name ?: ('mail-beleg.' . $ext),
                        'mime_type' => $attachment->getMimeType(),
                        'file_size' => strlen($content),
                        'file_hash' => $hash,
                        'status' => 'new',
                        'note' => 'Per E-Mail empfangen: ' . Str::limit((string) $message->getSubject(), 120),
                    ]);

                    try {
                        (new OcrService())->process($receipt->refresh());
                    } catch (Throwable $e) {
                        report($e); // OCR-Fehler darf den Import nicht stoppen
                    }

                    $created++;
                    $this->line('  + Beleg #' . $receipt->id . ': ' . $name);
                }

                // Mail als verarbeitet markieren bzw. verschieben.
                $message->setFlag('Seen');
                if (! empty($cfg['processed_folder'])) {
                    $message->move($cfg['processed_folder']);
                }
            } catch (Throwable $e) {
                report($e);
                $this->warn('  Fehler bei einer Mail: ' . $e->getMessage());
            }
        }

        $this->info("Fertig: $created Beleg(e) aus E-Mail angelegt.");

        return self::SUCCESS;
    }
}
