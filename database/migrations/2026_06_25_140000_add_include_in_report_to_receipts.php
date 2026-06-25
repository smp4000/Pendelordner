<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Steuert je Beleg, ob die Belegdatei im Steuerberater-Bericht (Modul 12)
 * angehängt wird. Standard: ja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->boolean('include_in_report')->default(true)->after('reviewed');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('include_in_report');
        });
    }
};
