<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sachkonto (Kontenrahmen) je Kontierungsposition (Modul 13). Ermöglicht das
 * Aufteilen eines Umsatzes auf mehrere Positionen mit eigenem Sachkonto –
 * zusätzlich zu Kategorie (G&V) und Kostenstelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->after('category_id')
                ->constrained('ledger_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_account_id');
        });
    }
};
