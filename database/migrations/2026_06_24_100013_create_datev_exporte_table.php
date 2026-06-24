<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATEV-Exporte (Modul 14 – nur Datenmodell-Vorbereitung, noch keine Implementierung).
 *
 * Hält die Kopfdaten eines DATEV-Buchungsstapels (EXTF-Format):
 * Berater-/Mandantennummer, Zeitraum, Kontenrahmen, Sachkontenlänge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('datev_exporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();
            $table->string('bezeichnung');

            $table->date('von_datum')->nullable();
            $table->date('bis_datum')->nullable();

            $table->string('berater_nummer')->nullable();
            $table->string('mandant_nummer')->nullable();
            $table->string('kontenrahmen')->default('skr03');
            $table->unsignedTinyInteger('sachkontenlaenge')->default(4);
            $table->unsignedSmallInteger('wirtschaftsjahr_beginn')->nullable()->comment('MMTT, z. B. 0101');

            $table->string('datei_pfad')->nullable();
            $table->unsignedInteger('anzahl_buchungen')->default(0);
            // entwurf | erzeugt | exportiert
            $table->string('status')->default('entwurf')->index();
            $table->timestamps();
        });

        // FK von kontierungen -> datev_exporte nachziehen
        Schema::table('kontierungen', function (Blueprint $table) {
            $table->foreign('datev_export_id')->references('id')->on('datev_exporte')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kontierungen', function (Blueprint $table) {
            $table->dropForeign(['datev_export_id']);
        });
        Schema::dropIfExists('datev_exporte');
    }
};
