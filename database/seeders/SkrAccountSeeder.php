<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importiert die DATEV-Standardkontenrahmen SKR03 und SKR04 aus
 * database/data/skr_accounts.json. Grundlage für die Auswertung beim
 * Steuerberater (eine Kategorie trägt je ein SKR03- und SKR04-Konto).
 *
 * Additiv und idempotent: ersetzt nur die Charts skr03/skr04, lässt die
 * übrigen Kontenrahmen (edtas/gastro/kfz) und manuell angelegte Konten
 * unangetastet. Auf dem Live-Server gefahrlos einzeln aufrufbar:
 *
 *   php artisan db:seed --class=SkrAccountSeeder
 */
class SkrAccountSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('data/skr_accounts.json');
        if (! is_file($file)) {
            $this->command?->warn('skr_accounts.json nicht gefunden – übersprungen.');

            return;
        }

        $rows = json_decode(file_get_contents($file), true) ?: [];

        // Nur die SKR-Kontenrahmen neu aufbauen.
        LedgerAccount::whereIn('chart', ['skr03', 'skr04'])->delete();

        $now = now();
        $insert = [];
        foreach ($rows as $r) {
            if (empty($r['number']) || empty($r['name'])) {
                continue;
            }
            $insert[] = [
                'chart' => $r['chart'] ?? 'skr03',
                'number' => $r['number'],
                'name' => mb_substr($r['name'], 0, 255),
                'group' => $r['group'] ? mb_substr($r['group'], 0, 255) : null,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            DB::table('ledger_accounts')->insert($chunk);
        }

        $this->command?->info(count($insert) . ' SKR03/04-Konten importiert.');
    }
}
