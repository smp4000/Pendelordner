<?php

namespace App\Console\Commands;

use App\Models\LedgerAccount;
use Illuminate\Console\Command;

/**
 * Trägt den USt-Satz für alle Sachkonten nach, bei denen er sich aus dem
 * Kontonamen ableiten lässt ("USt voll" = 19, "USt erm." = 7,
 * "USt frei"/"steuerfrei"/"nicht steuerbar" = 0) und die noch keinen Satz
 * gespeichert haben. Nicht-destruktiv: bereits gepflegte Sätze bleiben
 * unangetastet. Konten ohne erkennbaren Hinweis (z. B. "Shopping-Cards",
 * "Getränke", "Süßwaren") bleiben leer und werden in der Sachkonten-Verwaltung
 * von Hand gepflegt.
 */
class SachkontenUstAbleiten extends Command
{
    protected $signature = 'sachkonten:ust-ableiten';

    protected $description = 'USt-Sätze aus dem Kontonamen für alle noch leeren Sachkonten nachtragen';

    public function handle(): int
    {
        $count = 0;
        $offen = 0;

        LedgerAccount::query()->whereNull('tax_rate')->select(['id', 'name'])
            ->chunkById(500, function ($accounts) use (&$count, &$offen) {
                foreach ($accounts as $account) {
                    $rate = LedgerAccount::deriveTaxRateFromName($account->name);
                    if ($rate !== null) {
                        $account->newQuery()->whereKey($account->id)->update(['tax_rate' => $rate]);
                        $count++;
                    } else {
                        $offen++;
                    }
                }
            });

        $this->info("✓ {$count} Sachkonten mit USt-Satz aus dem Namen befüllt.");
        $this->line("{$offen} Konten ohne erkennbaren Hinweis bleiben leer (bei Bedarf in der Sachkonten-Verwaltung pflegen).");

        return self::SUCCESS;
    }
}
