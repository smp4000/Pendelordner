<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohnbereich (shop | werkstatt | gastro) für die Lohnzeilen, damit Werkstatt
 * und Gastronomie getrennt geplant werden können. Alle bestehenden Zeilen
 * bleiben dem Shop zugeordnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plan_staff_lines', function (Blueprint $table) {
            $table->string('area')->default('shop')->after('business_plan_id')
                ->comment('shop | werkstatt | gastro');
        });
    }

    public function down(): void
    {
        Schema::table('business_plan_staff_lines', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }
};
