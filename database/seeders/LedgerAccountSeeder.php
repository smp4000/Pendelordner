<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Importiert die eDTAS-Sachkonten (edtas/Kfz/Gastro) aus
 * database/data/ledger_accounts.json (PDF-Kontenpläne). Im Tankstellenbereich
 * wird ausschließlich mit eDTAS kontiert.
 */
class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('data/ledger_accounts.json');
        if (! is_file($file)) {
            $this->command?->warn('ledger_accounts.json nicht gefunden – übersprungen.');
        } else {
            $rows = json_decode(file_get_contents($file), true) ?: [];

            // Vollständiger Neuaufbau (reine Stammdaten aus dem Kontenplan).
            LedgerAccount::query()->delete();

            $now = now();
            $insert = [];
            foreach ($rows as $r) {
                if (empty($r['number']) || empty($r['name'])) {
                    continue;
                }
                $insert[] = [
                    'chart' => $r['chart'] ?? 'edtas',
                    'number' => $r['number'],
                    'name' => mb_substr($r['name'], 0, 255),
                    'group' => $r['group'] ? mb_substr($r['group'], 0, 255) : null,
                    // USt-Satz, soweit im Namen vermerkt (USt voll/erm./frei); sonst
                    // leer und in der Sachkonten-Verwaltung pflegbar.
                    'tax_rate' => LedgerAccount::deriveTaxRateFromName($r['name']),
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insert, 500) as $chunk) {
                DB::table('ledger_accounts')->insert($chunk);
            }

            $this->command?->info(count($insert) . ' Sachkonten (edtas/kfz/gastro) importiert.');
        }
    }
}
