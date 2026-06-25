<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sachkonto-Zuordnung direkt am Beleg (Modul 13): beim Bearbeiten einer
 * Rechnung kann das Aufwands-/Ertragskonto gewählt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('ledger_account_id')->nullable()->after('cost_center_id')
                ->constrained('ledger_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ledger_account_id');
        });
    }
};
