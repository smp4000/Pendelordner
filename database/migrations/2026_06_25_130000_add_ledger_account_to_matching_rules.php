<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sachkonto-Zuordnung an der Matching-Regel (Modul 4/13), damit aus einem
 * Umsatz erstellte Regeln auch das edtas/SKR-Konto auf wiederkehrende
 * Buchungen anwenden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_rules', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->after('cost_center_id')
                ->constrained('ledger_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matching_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_account_id');
        });
    }
};
