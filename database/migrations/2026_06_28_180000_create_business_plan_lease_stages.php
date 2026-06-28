<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacht-Staffelung (1.–4. Stufe): je Stufe ein Startzeitpunkt (Jahr/Monat),
 * ein Satz-Faktor in % auf die Umsatzpachtsätze und ein Festpacht-Betrag
 * (€/Monat). Aktiv ist je Monat die Stufe mit dem spätesten Start ≤ Monat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_plan_lease_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_no');
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedTinyInteger('start_month')->default(1);
            $table->decimal('rate_factor_pct', 6, 2)->default(100)->comment('Faktor auf die Umsatzpachtsätze');
            $table->decimal('festpacht_monthly', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['business_plan_id', 'stage_no'], 'bp_lease_stage_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_lease_stages');
    }
};
