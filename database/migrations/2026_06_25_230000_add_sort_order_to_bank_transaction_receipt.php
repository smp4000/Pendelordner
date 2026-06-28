<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manuelle Sortierung der einem Umsatz zugeordneten Belege (Modul 6). Bestimmt
 * die Reihenfolge in der Liste und im Steuerberater-Bericht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transaction_receipt', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transaction_receipt', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
