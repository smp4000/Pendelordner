<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tankstellen-/kundennummernabhängige Standard-Zuordnung je Lieferant:
 * die Verknüpfung Lieferant↔Tankstelle (Kundennummer) trägt jetzt zusätzlich
 * die Kostenstelle und das eDTAS-Konto für Rechnungen dieser Kundennummer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_customer_numbers', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()
                ->constrained('cost_centers', indexName: 'scn_cost_center_fk')->nullOnDelete();
            $table->string('edtas_account')->nullable()->comment('eDTAS-Konto für diese Kundennummer');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_customer_numbers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn('edtas_account');
        });
    }
};
