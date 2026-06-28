<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lohn-/Personalkostenberechnung (Stufe 3) für die Geschäftsplanung.
 *
 *   business_plan_staff_lines   Schicht-/Lohnzeilen (z. B. Kassenschicht Mo.–Do.)
 *   business_plan_staff_values  Std/Tag, Tage/Woche, Stundenlohn je Zeile und Jahr
 *
 * Daraus wird je Jahr das Personalkostenbudget berechnet (Löhne + Urlaub/
 * Krankheit + Lohnnebenkosten) und in die Plan-Position „Personalkosten"
 * geschrieben. Zwei Plan-Parameter steuern die Aufschläge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->decimal('payroll_overhead_pct', 5, 2)->default(25)->after('annual_repayment')
                ->comment('Lohnnebenkosten / AG-Anteil in %');
            $table->decimal('vacation_pct', 5, 2)->default(10)->after('payroll_overhead_pct')
                ->comment('Aufschlag Urlaub / Krankheit in %');
        });

        Schema::create('business_plan_staff_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->string('category')->nullable()->comment('Gruppe, z. B. Kassenschichten');
            $table->string('label');
            $table->boolean('is_deduction')->default(false)->comment('z. B. Eigenanteil Unternehmer (abziehen)');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('business_plan_id');
        });

        Schema::create('business_plan_staff_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_staff_line_id')->constrained('business_plan_staff_lines')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('hours_per_day', 8, 2)->default(0);
            $table->decimal('days_per_week', 5, 2)->default(0);
            $table->decimal('hourly_wage', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['business_plan_staff_line_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_staff_values');
        Schema::dropIfExists('business_plan_staff_lines');
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn(['payroll_overhead_pct', 'vacation_pct']);
        });
    }
};
