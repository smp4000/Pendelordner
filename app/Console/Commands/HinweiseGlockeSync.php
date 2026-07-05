<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Support\OffeneHinweisGlocke;
use Illuminate\Console\Command;

/**
 * Schiebt alle aktuell offenen Hinweise (note_open = true) in die
 * Kopfleisten-Glocke. Nützlich einmalig nach der Einführung der Glocke, damit
 * bereits vorhandene offene Hinweise dort auftauchen, ohne jeden Umsatz neu
 * speichern zu müssen.
 */
class HinweiseGlockeSync extends Command
{
    protected $signature = 'hinweise:sync-glocke';

    protected $description = 'Alle offenen Hinweise in die Kopfleisten-Glocke (Benachrichtigungen) übernehmen';

    public function handle(): int
    {
        $transactions = BankTransaction::query()->openNote()->get();

        foreach ($transactions as $transaction) {
            OffeneHinweisGlocke::sync($transaction);
        }

        $this->info($transactions->count() . ' offene(r) Hinweis(e) in die Glocke übernommen.');

        return self::SUCCESS;
    }
}
