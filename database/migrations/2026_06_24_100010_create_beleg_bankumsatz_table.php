<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung Beleg <-> Bankumsatz (Modul 5).
 *
 * n:m-Pivot mit Teilbetrag: ein Bankumsatz kann beliebig viele Belege enthalten
 * (z. B. Pappert 3.000 € = Rechnung 800 + 1.200 + 1.000), und ein Beleg kann auf
 * mehrere Umsätze aufgeteilt werden. 'betrag' = der diesem Umsatz zugeordnete
 * Anteil des Belegs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beleg_bankumsatz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankumsatz_id')->constrained('bankumsaetze')->cascadeOnDelete();
            $table->foreignId('beleg_id')->constrained('belege')->cascadeOnDelete();

            $table->decimal('betrag', 14, 2)->comment('dem Umsatz zugeordneter Anteil des Belegs');

            // manuell | automatisch | bestaetigt
            $table->string('zuordnungs_art')->default('manuell');
            $table->decimal('trefferquote', 5, 2)->nullable()->comment('Match-Score 0–100 % der Auto-Zuordnung');
            $table->text('notiz')->nullable();

            $table->timestamps();

            $table->unique(['bankumsatz_id', 'beleg_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beleg_bankumsatz');
    }
};
