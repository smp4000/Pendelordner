<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Im Tankstellenbereich wird ausschließlich mit edtas kontiert. SKR03/SKR04
 * werden entfernt: die beiden Konto-Felder (skr03_account/skr04_account) an
 * Kategorien, Lieferanten und Regeln werden zu einem einzigen edtas_account
 * zusammengeführt; SKR-Sachkonten und SKR-Kontenrahmen-Werte werden bereinigt.
 */
return new class extends Migration
{
    private array $tables = ['categories', 'suppliers', 'matching_rules'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'skr03_account') && ! Schema::hasColumn($t, 'edtas_account')) {
                Schema::table($t, fn (Blueprint $table) => $table->renameColumn('skr03_account', 'edtas_account'));
            }
            if (Schema::hasColumn($t, 'skr04_account')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('skr04_account'));
            }
        }

        // SKR-Sachkonten entfernen (operativ/steuerlich zählt nur noch edtas).
        DB::table('ledger_accounts')->whereIn('chart', ['skr03', 'skr04'])->delete();

        // Bereits gebuchte Kontierungen/Exporte auf edtas umstellen.
        if (Schema::hasColumn('account_assignments', 'chart_of_accounts')) {
            DB::table('account_assignments')->whereIn('chart_of_accounts', ['skr03', 'skr04'])->update(['chart_of_accounts' => 'edtas']);
        }
        if (Schema::hasColumn('datev_exports', 'chart_of_accounts')) {
            DB::table('datev_exports')->whereIn('chart_of_accounts', ['skr03', 'skr04'])->update(['chart_of_accounts' => 'edtas']);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'edtas_account')) {
                Schema::table($t, fn (Blueprint $table) => $table->renameColumn('edtas_account', 'skr03_account'));
            }
            if (! Schema::hasColumn($t, 'skr04_account')) {
                Schema::table($t, fn (Blueprint $table) => $table->string('skr04_account')->nullable());
            }
        }
    }
};
