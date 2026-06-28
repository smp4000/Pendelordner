<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geschäftsplanung (neues Modul): mehrjähriger Geschäftsplan je Tankstelle
 * nach dem Aufbau der Aral/GP-OIL-Vorlage.
 *
 *   business_plans            Kopf (Stammdaten, Planjahre, Finanzierung)
 *   business_plan_lines       Plan-Positionen (Umsatz-/Kostenzeilen)
 *   business_plan_line_values Wert je Position und Jahr (Betrag + Marge %)
 *
 * Die Geschäftsplanübersicht (Umsatz, Rohertrag, Kosten, Gewinn) wird daraus
 * berechnet und nicht gespeichert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('title');
            $table->string('ts_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to');
            $table->decimal('loan_amount', 14, 2)->default(0)->comment('Darlehensaufnahme');
            $table->decimal('private_draw', 14, 2)->default(0)->comment('privater Lebensbedarf / Entnahme pro Jahr');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('business_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->string('section')->comment('revenue | cost');
            $table->string('category')->nullable()->comment('Gruppe, z. B. Shop / Bistro');
            $table->string('label');
            $table->boolean('has_margin')->default(false)->comment('Umsatzzeile mit BVD-%');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_plan_id', 'section']);
        });

        Schema::create('business_plan_line_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_line_id')->constrained('business_plan_lines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('margin', 6, 2)->nullable()->comment('BVD % bei Umsatzzeilen');
            $table->timestamps();

            $table->unique(['business_plan_line_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_line_values');
        Schema::dropIfExists('business_plan_lines');
        Schema::dropIfExists('business_plans');
    }
};
