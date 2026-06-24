<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import-Protokolle (Modul 1).
 *
 * Protokolliert jeden Bankabruf / Datei-Import inkl. Dublettenstatistik.
 * Quelle: fints | mt940 | camt | csv | manuell.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_protokolle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankkonto_id')->nullable()->constrained('bankkonten')->nullOnDelete();
            $table->string('quelle')->index();
            $table->string('dateiname')->nullable();

            $table->unsignedInteger('anzahl_gesamt')->default(0);
            $table->unsignedInteger('anzahl_neu')->default(0);
            $table->unsignedInteger('anzahl_dubletten')->default(0);
            $table->unsignedInteger('anzahl_fehler')->default(0);

            // laufend | erfolgreich | teilweise | fehler
            $table->string('status')->default('laufend')->index();
            $table->text('meldung')->nullable();
            $table->timestamp('gestartet_at')->nullable();
            $table->timestamp('beendet_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_protokolle');
    }
};
