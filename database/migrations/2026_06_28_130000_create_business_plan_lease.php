<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pachtberechnung (Stufe 4) für die Geschäftsplanung.
 *
 * Pacht - Station = Shopumsatzpacht + Festpacht
 *   Shopumsatzpacht = Summe je Bemessungsgrundlage (Umsatz × Satz %),
 *                     anteilig ab dem Startmonat im ersten Jahr
 *   Festpacht       = fester €-Betrag je Monat ab einer Startstufe
 *
 *   business_plan_lease_bases  Bemessungsgrundlagen der Umsatzpacht (Satz %)
 *   + Plan-Felder für Start der Umsatzpacht und Festpacht
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('umsatzpacht_start_year')->nullable()->after('vacation_pct');
            $table->unsignedTinyInteger('umsatzpacht_start_month')->default(1)->after('umsatzpacht_start_year');
            $table->decimal('festpacht_monthly', 14, 2)->default(0)->after('umsatzpacht_start_month');
            $table->unsignedSmallInteger('festpacht_start_year')->nullable()->after('festpacht_monthly');
            $table->unsignedTinyInteger('festpacht_start_month')->default(1)->after('festpacht_start_year');
        });

        Schema::create('business_plan_lease_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_plan_id')->constrained('business_plans')->cascadeOnDelete();
            $table->string('label');
            $table->string('source')->default('manual')->comment('tabak | wasch | shop_rest | manual');
            $table->decimal('manual_amount', 14, 2)->default(0)->comment('Jahresumsatz bei source=manual');
            $table->decimal('rate_pct', 6, 3)->default(0)->comment('Umsatzpachtsatz in %');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('business_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_plan_lease_bases');
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn([
                'umsatzpacht_start_year', 'umsatzpacht_start_month',
                'festpacht_monthly', 'festpacht_start_year', 'festpacht_start_month',
            ]);
        });
    }
};
