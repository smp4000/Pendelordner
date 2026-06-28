<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zusätzliche Annahmen für die Liquiditätsplanung (Stufe 2) eines
 * Geschäftsplans: Anfangsbestand, pauschaler USt-Satz und jährliche Tilgung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->decimal('opening_balance', 14, 2)->default(0)->after('private_draw')
                ->comment('Anfangsbestand Liquidität');
            $table->decimal('vat_rate', 5, 2)->default(19)->after('opening_balance')
                ->comment('pauschaler USt-Satz % für die Liquidität');
            $table->decimal('annual_repayment', 14, 2)->default(0)->after('vat_rate')
                ->comment('jährliche Darlehenstilgung');
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'vat_rate', 'annual_repayment']);
        });
    }
};
