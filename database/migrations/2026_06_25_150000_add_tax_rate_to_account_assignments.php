<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Steuersatz je Kontierungsposition (Modul 13). Ermöglicht das Aufteilen eines
 * Umsatzes auf mehrere Positionen mit unterschiedlichen Steuersätzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('tax_key');
        });
    }

    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
