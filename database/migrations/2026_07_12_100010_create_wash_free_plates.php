<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennzeichen für Freiwäschen (Modul Waschumsätze) – station­übergreifend.
 *
 * Kategorien: "eigen" (Eigenfahrzeuge des Inhabers), "mitarbeiter"
 * (Mitarbeiter-Freiwäschen, steuerlich gesondert = Sachbezug) und "test"
 * (Testwäschen, intern). Beim Import wird jede Wäsche mit passendem Kennzeichen
 * entsprechend markiert. Voll pflegbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wash_free_plates', function (Blueprint $table) {
            $table->id();
            // Anzeige-Kennzeichen wie eingegeben (z. B. "FD-CW21").
            $table->string('plate');
            // Normalisiert (Großbuchstaben, ohne Leer-/Sonderzeichen) für den Abgleich.
            $table->string('normalized');
            // eigen | mitarbeiter | test
            $table->string('category')->default('eigen');
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('normalized', 'wash_plate_norm_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wash_free_plates');
    }
};
