<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohn-Detail (Stufe 6): Aufteilung Festangestellte/Aushilfen mit eigenen
 * AG-Anteilen sowie Sonntags-/Feiertags-/Nachtzuschläge für die
 * Personalkostenberechnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->decimal('staff_fest_pct', 5, 2)->default(0)->after('vacation_pct')
                ->comment('Anteil Festangestellte in %');
            $table->decimal('ag_pct_fest', 5, 2)->default(22.5)->after('staff_fest_pct')
                ->comment('AG-Anteil Festangestellte in %');
            $table->decimal('ag_pct_aushilfe', 5, 2)->default(31.15)->after('ag_pct_fest')
                ->comment('AG-Anteil Aushilfen in %');
            $table->decimal('sonntag_hours', 8, 2)->default(0)->after('ag_pct_aushilfe');
            $table->decimal('sonntag_pct', 5, 2)->default(0)->after('sonntag_hours');
            $table->decimal('feiertag_hours', 8, 2)->default(0)->after('sonntag_pct');
            $table->decimal('feiertag_pct', 5, 2)->default(0)->after('feiertag_hours');
            $table->decimal('nacht_hours', 8, 2)->default(0)->after('feiertag_pct');
            $table->decimal('nacht_pct', 5, 2)->default(25)->after('nacht_hours');
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn([
                'staff_fest_pct', 'ag_pct_fest', 'ag_pct_aushilfe',
                'sonntag_hours', 'sonntag_pct', 'feiertag_hours', 'feiertag_pct',
                'nacht_hours', 'nacht_pct',
            ]);
        });
    }
};
