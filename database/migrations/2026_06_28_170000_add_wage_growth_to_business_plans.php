<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohnentwicklung (Mindestlohn-Anpassung): jährliche Steigerung der Stundenlöhne
 * in %. Ist sie > 0, werden die Löhne der Folgejahre automatisch aus dem ersten
 * Planjahr hochgerechnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->decimal('wage_growth_pct', 5, 2)->default(0)->after('nacht_pct')
                ->comment('jährliche Lohnsteigerung in %');
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn('wage_growth_pct');
        });
    }
};
