<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stationsnummer (für die Zuordnung der Kassenabrechnung zur Tankstelle) und
 * Kraftstoff-Provision in Cent je Liter – je Tankstelle einstellbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('station_number')->nullable()->comment('Aral-Stationsnummer');
            $table->decimal('fuel_commission_ct', 6, 3)->default(2.8)->comment('Kraftstoff-Provision in Cent je Liter');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['station_number', 'fuel_commission_ct']);
        });
    }
};
