<?php

namespace App\Console\Commands;

use App\Models\BankAccount;
use App\Services\Bank\FinTsService;
use App\Services\Bank\FinTsTanRequiredException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Automatischer FinTS-Umsatzabruf (Modul 1).
 *
 * Ruft für alle aktiven, FinTS-fähigen Bankkonten die Umsätze ab und importiert
 * sie mit Dublettenprüfung. Wird per Scheduler zeitgesteuert ausgeführt
 * (siehe routes/console.php) oder manuell:
 *
 *   php artisan bank:fetch                # alle FinTS-Konten
 *   php artisan bank:fetch --account=3    # nur Konto 3
 *   php artisan bank:fetch --days=30      # Zeitraum 30 Tage
 */
class FetchBankTransactions extends Command
{
    protected $signature = 'bank:fetch
        {--account= : Nur ein bestimmtes Bankkonto (ID)}
        {--days= : Abrufzeitraum in Tagen (Standard aus Konfiguration)}';

    protected $description = 'Ruft Bankumsätze aller FinTS-fähigen Konten automatisch ab';

    public function handle(FinTsService $fints): int
    {
        $days = (int) ($this->option('days') ?: config('pendelordner.fints.default_days', 90));
        $from = Carbon::now()->subDays($days);
        $to = Carbon::now();

        $accounts = BankAccount::query()
            ->where('active', true)
            ->where('fints_enabled', true)
            ->whereNotNull('fints_connection_id')
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Keine FinTS-fähigen Konten gefunden.');

            return self::SUCCESS;
        }

        $hadError = false;

        foreach ($accounts as $account) {
            $this->line("Abruf: {$account->label} ({$account->iban}) …");

            try {
                $log = $fints->fetchAccount($account, $from, $to);
                $this->info("  ✓ {$log->new_count} neu, {$log->duplicate_count} Dubletten, {$log->error_count} Fehler.");
            } catch (FinTsTanRequiredException $e) {
                $hadError = true;
                $this->warn('  ⚠ TAN erforderlich – Konto übersprungen. ' . $e->getMessage());
                $account->fintsConnection?->forceFill(['last_message' => 'TAN erforderlich – automatischer Abruf nicht möglich.'])->saveQuietly();
            } catch (Throwable $e) {
                $hadError = true;
                report($e);
                $this->error('  ✗ Fehler: ' . $e->getMessage());
                $account->fintsConnection?->forceFill(['last_message' => 'Fehler: ' . $e->getMessage()])->saveQuietly();
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}
