<?php

/**
 * Laravel – Router-Skript für den eingebauten PHP-Entwicklungsserver
 * (php artisan serve). Liegt bewusst im Projektstamm, damit `php artisan serve`
 * diese Datei statt der vendor-Variante nutzt und der Serverstart unabhängig
 * vom vendor-Verzeichnis funktioniert.
 */
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Emuliert Apaches mod_rewrite: existierende Dateien in /public direkt ausliefern.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
