<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATEV-Exporte (Modul 14 – nur Datenmodell-Vorbereitung). Kopfdaten eines
 * DATEV-Buchungsstapels (EXTF): Berater-/Mandantennummer, Zeitraum,
 * Kontenrahmen, Sachkontenlänge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datev_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('label');                    // Bezeichnung

            $table->date('from_date')->nullable();      // von
            $table->date('to_date')->nullable();        // bis

            $table->string('consultant_number')->nullable(); // Berater-Nr.
            $table->string('client_number')->nullable();     // Mandanten-Nr.
            $table->string('chart_of_accounts')->default('skr03');
            $table->unsignedTinyInteger('account_length')->default(4); // Sachkontenlänge
            $table->unsignedSmallInteger('fiscal_year_start')->nullable()->comment('MMTT, z. B. 0101');

            $table->string('file_path')->nullable();
            $table->unsignedInteger('entry_count')->default(0); // Anzahl Buchungen
            $table->string('status')->default('draft')->index(); // draft|generated|exported
            $table->timestamps();
        });

        // FK von account_assignments -> datev_exports nachziehen
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->foreign('datev_export_id')->references('id')->on('datev_exports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_assignments', function (Blueprint $table) {
            $table->dropForeign(['datev_export_id']);
        });
        Schema::dropIfExists('datev_exports');
    }
};
