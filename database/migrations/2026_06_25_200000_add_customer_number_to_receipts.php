<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aus der Rechnung erkannte Kundennummer (Modul 3/4). Dient der automatischen
 * Zuordnung zu Lieferant + Tankstelle über die Verknüpfungstabelle
 * supplier_customer_numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('customer_number')->nullable()->after('supplier_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('customer_number');
        });
    }
};
