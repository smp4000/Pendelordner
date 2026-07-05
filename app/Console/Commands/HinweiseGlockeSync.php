<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Models\User;
use App\Support\OffeneHinweisGlocke;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Schiebt alle aktuell offenen Hinweise (note_open = true) in die
 * Kopfleisten-Glocke und gibt eine Diagnose aus. Nützlich einmalig nach der
 * Einführung der Glocke bzw. wenn die Glocke leer bleibt.
 */
class HinweiseGlockeSync extends Command
{
    protected $signature = 'hinweise:sync-glocke';

    protected $description = 'Offene Hinweise in die Kopfleisten-Glocke übernehmen (mit Diagnose)';

    public function handle(): int
    {
        // 1. Ist die Benachrichtigungs-Tabelle überhaupt vorhanden?
        if (! Schema::hasTable('notifications')) {
            $this->error('Die Tabelle "notifications" fehlt. Bitte zuerst ausführen: php artisan migrate');

            return self::FAILURE;
        }
        $this->info('✓ Tabelle "notifications" vorhanden.');

        // 2. Nutzer und offene Hinweise zählen.
        $userCount = User::count();
        $this->line('Nutzer im System: ' . $userCount);
        if ($userCount === 0) {
            $this->error('Kein Nutzer vorhanden – es gibt niemanden, dem die Glocke gehört.');

            return self::FAILURE;
        }

        $transactions = BankTransaction::query()->openNote()->get();
        $this->line('Offene Hinweise (note_open = true, mit Text): ' . $transactions->count());

        if ($transactions->isEmpty()) {
            $this->warn('Es gibt aktuell keine offenen Hinweise – daher bleibt die Glocke leer.');
            $this->line('Tipp: einen Umsatz öffnen, "Mitteilung an Steuerberater" schreiben und "⚠ Erfordert Reaktion" ankreuzen.');

            return self::SUCCESS;
        }

        // 3. In die Glocke übernehmen.
        foreach ($transactions as $transaction) {
            OffeneHinweisGlocke::sync($transaction);
        }

        // 4. Ergebnis prüfen.
        $notifications = \Illuminate\Notifications\DatabaseNotification::count();
        $this->info('✓ ' . $transactions->count() . ' Hinweis(e) übernommen. Benachrichtigungen in der Glocke gesamt: ' . $notifications);
        $this->line('Jetzt im Browser mit Strg+Shift+R neu laden – die Glocke sollte die Hinweise zeigen.');

        return self::SUCCESS;
    }
}
