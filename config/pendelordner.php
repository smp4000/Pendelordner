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
        'vorschlag_ab' => 60,
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
        'standard_kontenrahmen' => 'skr03',
        // Standard-Geldkonten (Gegenkonto bei Bankbuchungen) je Kontenrahmen.
        'geldkonten' => [
            'skr03' => ['bank' => '1200', 'kasse' => '1000'],
            'skr04' => ['bank' => '1800', 'kasse' => '1600'],
        ],
        // Sammelkonto, wenn keine Kategorie/kein Konto ermittelbar ist.
        'sammelkonto' => [
            'skr03' => '1590', // Verrechnungskonto / Klärung
            'skr04' => '1370',
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
        'product_id' => env('FINTS_PRODUCT_REGISTRATION', 'PENDELORDNER'),
        'product_version' => env('FINTS_PRODUCT_VERSION', '1.0'),
        // Standard-Abrufzeitraum in Tagen.
        'default_days' => 90,
    ],

];
