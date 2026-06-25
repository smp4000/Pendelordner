<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mitteilung an den Steuerberater pro Bankumsatz (Modul 12) – wird im
 * Steuerberater-Bericht unter dem Umsatz fett ausgegeben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->text('accountant_note')->nullable()->after('ledger_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('accountant_note');
        });
    }
};
