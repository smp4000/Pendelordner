<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Setzt die Bewegungsdaten zurück (Bankumsätze, Belege, Kassenumsätze,
 * Kontierungen, Exporte, Steuerbüro-Dateien/-Hinweise) und lässt die
 * Stammdaten (Betriebe, Konten, Kategorien, Sachkonten, Regeln, Lieferanten,
 * Geschäftspläne, Hinweis-Texte) unangetastet.
 */
class ResetTransactionData extends Command
{
    protected $signature = 'daten:zuruecksetzen {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Bewegungsdaten löschen (Stammdaten bleiben erhalten)';

    /** Bewegungsdaten-Tabellen (in FK-sicherer Reihenfolge egal – FK-Prüfung wird deaktiviert). */
    private array $tables = [
        'account_assignments',
        'bank_transaction_receipt',
        'bank_transaction_cost_center',
        'cost_center_receipt',
        'receipts',
        'bank_transactions',
        'import_logs',
        'steuer_documents',
        'pos_sales',
        'datev_exports',
        'report_note_lines',
        'report_notes',
    ];

    public function handle(): int
    {
        $this->warn('Folgende Bewegungsdaten werden GELÖSCHT (Stammdaten bleiben):');
        $this->line('  ' . implode(', ', $this->tables));

        if (! $this->option('force') && ! $this->confirm('Wirklich löschen?')) {
            $this->info('Abgebrochen.');

            return self::SUCCESS;
        }

        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  geleert: {$table}");
            }
        }
        Schema::enableForeignKeyConstraints();

        $this->info('Bewegungsdaten zurückgesetzt. Stammdaten sind erhalten.');

        return self::SUCCESS;
    }
}
