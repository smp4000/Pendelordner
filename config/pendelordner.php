<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Belegarchiv
    |--------------------------------------------------------------------------
    | Disk und Ordnerstruktur des Belegarchivs (Modul 3). Dateien werden unter
    | /belege/JAHR/MONAT/TAG abgelegt.
    */
    'belege_disk' => env('BELEGE_DISK', 'belege'),

    /*
    |--------------------------------------------------------------------------
    | OCR (Tesseract)
    |--------------------------------------------------------------------------
    */
    'ocr' => [
        'tesseract_pfad' => env('TESSERACT_PATH', 'tesseract'),
        'sprache' => env('TESSERACT_LANG', 'deu'),
        // Erst versuchen, eingebetteten PDF-Text zu lesen (smalot/pdfparser),
        // nur bei zu wenig Text auf Tesseract-OCR ausweichen.
        'pdf_text_mindestlaenge' => 80,
    ],

    /*
    |--------------------------------------------------------------------------
    | Matching-Engine (Modul 4)
    |--------------------------------------------------------------------------
    | Schwellwerte für die automatische Beleg-/Umsatz-Zuordnung.
    */
    'matching' => [
        // Vorschlag ab dieser Trefferquote (%) anzeigen.
        // 50 = ein exakter Betragstreffer genügt bereits als Vorschlag
        // (Betrag hat Gewicht 50); Datum/Lieferant/IBAN erhöhen das Ranking.
        'vorschlag_ab' => 50,
        // Ab dieser Trefferquote (%) automatisch als bestätigt vormerken.
        'auto_ab' => 95,
        // Toleranz für Betragsabgleich in Euro.
        'betrag_toleranz' => 0.01,
        // Maximale Tagesdifferenz zwischen Beleg- und Buchungsdatum.
        'datum_toleranz_tage' => 14,
        // Gewichtung der Einzelkriterien (Summe = 100).
        'gewichtung' => [
            'betrag' => 50,
            'lieferant' => 30,
            'datum' => 10,
            'iban' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Kontierung / DATEV (Modul 13/14)
    |--------------------------------------------------------------------------
    */
    'kontierung' => [
        'standard_kontenrahmen' => 'edtas',
        // Standard-Geldkonten (Gegenkonto bei Bankbuchungen) je Kontenrahmen.
        'geldkonten' => [
            'edtas' => ['bank' => '1200', 'kasse' => '1000'],
        ],
        // Sammelkonto, wenn keine Kategorie/kein Konto ermittelbar ist.
        'sammelkonto' => [
            'edtas' => '1590', // Verrechnungskonto / Klärung
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FinTS (Bankanbindung, Modul 1)
    |--------------------------------------------------------------------------
    | Produktregistrierung bei der Deutschen Kreditwirtschaft. Pro Zugang in der
    | DB überschreibbar.
    */
    'fints' => [
        // Leerer .env-Wert würde den Default überschreiben -> mit ?: absichern.
        'product_id' => env('FINTS_PRODUCT_REGISTRATION') ?: 'PENDELORDNER',
        'product_version' => env('FINTS_PRODUCT_VERSION') ?: '1.0',
        // Standard-Abrufzeitraum in Tagen.
        'default_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rechnungseingang per E-Mail (Modul 3)
    |--------------------------------------------------------------------------
    | Ein IMAP-Postfach (z. B. belege@deinedomain.de) wird per Zeitplan
    | abgefragt; PDF-/Bild-Anhänge werden als Belege gespeichert und per OCR
    | ausgewertet. Leite deine Rechnungs-Mails per Weiterleitungsregel an dieses
    | Postfach. Abruf: "php artisan belege:fetch-mail" (im Scheduler hinterlegt).
    */
    'mail_ingest' => [
        'enabled' => (bool) env('MAIL_INGEST_ENABLED', false),
        'host' => env('MAIL_INGEST_HOST'),
        'port' => (int) env('MAIL_INGEST_PORT', 993),
        'encryption' => env('MAIL_INGEST_ENCRYPTION', 'ssl'), // ssl|tls|null
        'validate_cert' => (bool) env('MAIL_INGEST_VALIDATE_CERT', true),
        'username' => env('MAIL_INGEST_USERNAME'),
        'password' => env('MAIL_INGEST_PASSWORD'),
        'folder' => env('MAIL_INGEST_FOLDER', 'INBOX'),
        // Verarbeitete Mails hierhin verschieben (leer = nur als gelesen markieren).
        'processed_folder' => env('MAIL_INGEST_PROCESSED_FOLDER', ''),
        // Welcher Betrieb neuen Mail-Belegen zugeordnet wird (leer = keiner).
        'business_id' => env('MAIL_INGEST_BUSINESS_ID') ?: null,
        // Zugelassene Anhang-Endungen.
        'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff'],
        // Abrufzeit (HH:MM) bzw. -frequenz im Scheduler.
        'fetch_time' => env('MAIL_INGEST_FETCH_TIME', '*/15'), // alle 15 Min
    ],

    /*
    |--------------------------------------------------------------------------
    | Cron-Token
    |--------------------------------------------------------------------------
    | Geheimer Schlüssel, mit dem die per URL aufgerufenen Cron-Endpunkte
    | (/cron/...) abgesichert sind (z. B. für den all-inkl-URL-Cronjob).
    */
    'cron_token' => env('CRON_TOKEN'),

];
