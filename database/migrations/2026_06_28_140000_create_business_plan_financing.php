<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finanzierung/Kapitalbedarf + Gewerbesteuer (Stufe 5) für die Geschäftsplanung.
 *
 *   business_plan_financings  Kapitalbedarf-Positionen (Summe = Darlehen)
 *   + Plan-Felder: Zinssatz, Gewerbesteuer-Hebesatz und -Schalter
 *
 * Der Kapitalbedarf wird als Darlehen aufgenommen, der Zinssatz erzeugt die
 * jährlichen Zinsen (Position „Zinsen- und Geldkosten"). Die Gewerbesteuer
 * wird per Hebesatz berechnet (handels- vs. steuerrechtlicher Gewinn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->decimal('interest_rate', 6, 3)->default(0)->after('festpacht_start_month')
                ->comment('Zinssatz Finanzierung in %');
            $table->boolean('gewst_enabled')->default(false)->after('interest_rate')
                ->comment('Gewerbesteuer einbeziehen?');
            $table->decimal('gewst_hebesatz', 6, 2)->default(0)->after('gewst_enabled')
                ->comment('Gewerbesteuer-Hebesatz in %');
        });

        Schema::create('business_plan_financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->string('label');
            $table->string('finance_type')->nullable()->comment('Art der Finanzierung');
            $table->decimal('amount', 14, 2)->default(0)->comment('Kapitalbedarf');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('business_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_financings');
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'gewst_enabled', 'gewst_hebesatz']);
        });
    }
};
