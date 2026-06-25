<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorie je Kontierungsposition (Modul 13/10). Ermöglicht das Aufteilen
 * eines Umsatzes auf mehrere Kategorien (z. B. Kosten / Lotto neutral /
 * Provision) für die Gewinn- und Verlustrechnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('cost_center_id')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
