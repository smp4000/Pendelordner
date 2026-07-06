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

        $created = 0;

        foreach ($messages as $message) {
            try {
                // Jeder Anhang wird ein eigener Beleg – mehrere Rechnungen in
                // einer Mail werden also einzeln getrennt.
                foreach ($message->getAttachments() as $attachment) {
                    // Inline-Anhänge (z. B. Logos/Signaturbilder in der Mail)
                    // sind keine Rechnungen -> überspringen.
                    if (strtolower((string) $attachment->getDisposition()) === 'inline') {
                        continue;
                    }

                    $name = (string) $attachment->getName();
                    $ext = strtolower((string) ($attachment->getExtension() ?: pathinfo($name, PATHINFO_EXTENSION)));
                    $content = (string) ($attachment->getContent() ?? '');

                    $receipt = $this->storeAttachmentAsReceipt(
                        $content, $name, $ext, $attachment->getMimeType(), (string) $message->getSubject()
                    );

                    if ($receipt) {
                        $created++;
                        $this->line('  + Beleg #' . $receipt->id . ': ' . $name);
                    } else {
                        $this->line('  = übersprungen (Dublette oder unzulässiger Anhang): ' . $name);
                    }
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

    /**
     * Speichert einen einzelnen Mail-Anhang als eigenen Beleg (inkl. OCR). So
     * wird JEDER Anhang einer Mail zu einem separaten Beleg – mehrere
     * Rechnungen in einer Mail werden getrennt.
     *
     * Gibt null zurück, wenn die Endung nicht zugelassen, der Inhalt leer oder
     * es eine Dublette (gleicher Datei-Hash) ist.
     */
    public function storeAttachmentAsReceipt(string $content, string $name, string $ext, ?string $mime, string $subject): ?Receipt
    {
        $cfg = config('pendelordner.mail_ingest');
        $allowed = array_map('strtolower', $cfg['extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff']);
        $ext = strtolower($ext);

        if (! in_array($ext, $allowed, true) || $content === '') {
            return null;
        }

        // Zu kleine Anhänge (Logos/Icons) überspringen.
        $minBytes = (int) ($cfg['min_bytes'] ?? 0);
        if ($minBytes > 0 && strlen($content) < $minBytes) {
            return null;
        }

        // Dublettenprüfung über den Datei-Hash (gleiche Datei doppelt).
        $hash = hash('sha256', $content);
        if (Receipt::withTrashed()->where('file_hash', $hash)->exists()) {
            return null;
        }

        $disk = Storage::disk(config('pendelordner.belege_disk', 'belege'));
        $path = date('Y/m') . '/' . Str::random(40) . '.' . $ext;
        $disk->put($path, $content);

        $receipt = Receipt::create([
            'type' => ReceiptType::IncomingInvoice,
            'business_id' => $cfg['business_id'] ?: null,
            'file_path' => $path,
            'file_name' => $name ?: ('mail-beleg.' . $ext),
            'mime_type' => $mime,
            'file_size' => strlen($content),
            'file_hash' => $hash,
            'status' => 'new',
            'note' => 'Per E-Mail empfangen: ' . Str::limit($subject, 120),
        ]);

        try {
            (new OcrService())->process($receipt->refresh());
        } catch (Throwable $e) {
            report($e); // OCR-Fehler darf den Import nicht stoppen
        }

        return $receipt;
    }
}
