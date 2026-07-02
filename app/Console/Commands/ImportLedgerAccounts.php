<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Importiert Sachkonten aus edtas-Kontenplan-PDFs (Modul 13).
 *
 *   php artisan accounts:import --dir="C:/Users/chris/Downloads"
 *
 * Der Kontenrahmen wird am Dateinamen erkannt (edtas / kfz / gastro). Das
 * Ergebnis wird nach database/data/ledger_accounts.json geschrieben UND direkt
 * in die Tabelle ledger_accounts übernommen (vollständiger Neuaufbau).
 */
class ImportLedgerAccounts extends Command
{
    protected $signature = 'accounts:import {--dir= : Verzeichnis mit den Kontenplan-PDFs}';

    protected $description = 'Liest die edtas-Kontenplan-PDFs ein und befüllt die Sachkonten';

    public function handle(): int
    {
        $dir = rtrim($this->option('dir') ?: base_path('storage/app/kontenplaene'), '/\\');
        if (! is_dir($dir)) {
            $this->error("Verzeichnis nicht gefunden: {$dir}");

            return self::FAILURE;
        }

        $files = glob($dir . '/*.pdf') ?: [];
        if (empty($files)) {
            $this->error("Keine PDF-Dateien in {$dir} gefunden.");

            return self::FAILURE;
        }

        $all = [];
        foreach ($files as $file) {
            $name = strtolower(basename($file));
            if (! str_contains($name, 'kontenplan')) {
                continue; // nur Kontenpläne, keine Zuordnungslisten
            }
            [$chart, $layout] = match (true) {
                str_contains($name, 'kfz') => ['kfz', 'leading'],
                str_contains($name, 'gastro') => ['gastro', 'trailing'],
                default => ['edtas', 'edtas'],
            };

            try {
                $accounts = $this->parseChart($file, $layout);
                foreach ($accounts as $a) {
                    $a['chart'] = $chart;
                    $all[] = $a;
                }
                $this->info(sprintf('  %s (%s): %d Konten', basename($file), $chart, count($accounts)));
            } catch (Throwable $e) {
                $this->error('  Fehler bei ' . basename($file) . ': ' . $e->getMessage());
            }
        }

        if (empty($all)) {
            $this->warn('Keine Konten erkannt.');

            return self::FAILURE;
        }

        // Dubletten über alle Dateien hinweg entfernen (z. B. doppelte PDFs "…-1.pdf").
        $deduped = [];
        foreach ($all as $a) {
            $deduped[$a['chart'] . '-' . $a['number']] = $a;
        }
        $all = array_values($deduped);

        // JSON sichern (als Seeder-Quelle)
        $jsonPath = database_path('data/ledger_accounts.json');
        @mkdir(dirname($jsonPath), 0775, true);
        file_put_contents($jsonPath, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // In die Tabelle übernehmen. Nur die per PDF erzeugten Kontenrahmen
        // ersetzen – (nur eDTAS-Kontenrahmen).
        $charts = array_values(array_unique(array_column($all, 'chart')));
        LedgerAccount::whereIn('chart', $charts)->delete();
        $now = now();
        $rows = array_map(fn ($a) => [
            'chart' => $a['chart'],
            'number' => $a['number'],
            'name' => mb_substr($a['name'], 0, 255),
            'group' => $a['group'] ? mb_substr($a['group'], 0, 255) : null,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $all);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('ledger_accounts')->insert($chunk);
        }

        $this->info('Gesamt: ' . count($all) . ' Sachkonten importiert (JSON: ' . $jsonPath . ').');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{number: string, name: string, group: ?string}>
     */
    private function parseChart(string $path, string $layout): array
    {
        $txt = (new PdfParser())->parseFile($path)->getText();
        $txt = preg_replace('/Konto\s+Kontobezeichnung|Kontenplan[^\n]*ab \d{4}|Zuordnung GA[^\n]*|Kontenklasse|\d\s*-\s*Konten[^\n]*/u', ' ', $txt);
        $txt = preg_replace('/\s+/u', ' ', $txt);

        $parts = preg_split('/(?<!\d)(\d{4})(?!\d)/u', $txt, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $pre = array_shift($parts);
        $pairs = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $pairs[] = [$parts[$i], $parts[$i + 1]];
        }

        $out = [];
        if ($layout === 'edtas') {
            [, $pendingGa] = $this->splitTrailingGa($pre);
            foreach ($pairs as [$num, $text]) {
                [$name, $nextGa] = $this->splitTrailingGa($text);
                $out[] = ['number' => $num, 'name' => $name, 'group' => $pendingGa];
                $pendingGa = $nextGa;
            }
        } elseif ($layout === 'trailing') {
            foreach ($pairs as [$num, $text]) {
                [$name, $ga] = $this->splitTrailingGa($text);
                $out[] = ['number' => $num, 'name' => $name, 'group' => $ga];
            }
        } else { // leading (kfz)
            foreach ($pairs as [$num, $text]) {
                $text = trim($text);
                $ga = null;
                $name = $text;
                if (preg_match('/^([BD],\s?)(.*)$/u', $text, $m)) {
                    $rest = $m[2];
                    // Name beginnt mit Upper+lower ODER Akronym (2+ Großbuchstaben) ODER Ziffer
                    if (preg_match('/^(.*?[a-zäöüß])((?:[A-ZÄÖÜ]{2,}|[A-ZÄÖÜ][a-zäöü]|\d).*)$/u', $rest, $mm)) {
                        $ga = trim($m[1] . $mm[1]);
                        $name = trim($mm[2]);
                    } else {
                        $name = trim($rest);
                        $ga = trim($m[1]);
                    }
                }
                $out[] = ['number' => $num, 'name' => $name, 'group' => $ga];
            }
        }

        // Bereinigen + deduplizieren
        $seen = [];
        $clean = [];
        foreach ($out as $a) {
            $a['name'] = trim(preg_replace('/\s+/', ' ', $a['name']));
            if ($a['name'] === '' || isset($seen[$a['number']])) {
                continue;
            }
            $seen[$a['number']] = true;
            $clean[] = $a;
        }

        return $clean;
    }

    /** Trennt eine abschließende GA-Zuordnung ("B, …"/"D, …") vom Text. */
    private function splitTrailingGa(string $t): array
    {
        if (preg_match('/\s*([BD],\s?[A-ZÄÖÜ][A-Za-zÄÖÜäöüß0-9 ,\.\-\/()&]*)$/u', $t, $m)) {
            return [trim(substr($t, 0, -strlen($m[0]))), trim($m[1])];
        }

        return [trim($t), null];
    }
}
