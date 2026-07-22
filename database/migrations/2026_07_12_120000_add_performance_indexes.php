<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance-Indizes für häufige Dashboard-/Auswertungs-Filter:
 * - bank_transactions.note_open / .split_open (Widgets „Offene Hinweise/
 *   Aufteilungen" prüfen bei jedem Dashboard-Load exists()/count()),
 * - wash_payments.payment_date (Jahres-Auswertung „Alle Stationen").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->index('note_open', 'bt_note_open_idx');
            $table->index('split_open', 'bt_split_open_idx');
        });

        Schema::table('wash_payments', function (Blueprint $table) {
            $table->index('payment_date', 'wash_pay_pdate_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex('bt_note_open_idx');
            $table->dropIndex('bt_split_open_idx');
        });

        Schema::table('wash_payments', function (Blueprint $table) {
            $table->dropIndex('wash_pay_pdate_idx');
        });
    }
};
