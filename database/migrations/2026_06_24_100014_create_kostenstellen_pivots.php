<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mehrfach-Kostenstellen-Zuordnung (Modul 9 – "Mehrfachzuordnung vorbereiten").
 *
 * Erlaubt die anteilige Verteilung eines Bankumsatzes bzw. Belegs auf mehrere
 * Kostenstellen. Im einfachen Fall genügt das Feld kostenstelle_id am
 * Hauptdatensatz; diese Pivots stehen für die spätere Aufteilung bereit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankumsatz_kostenstelle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankumsatz_id')->constrained('bankumsaetze')->cascadeOnDelete();
            $table->foreignId('kostenstelle_id')->constrained('kostenstellen')->cascadeOnDelete();
            $table->decimal('betrag', 14, 2)->nullable();
            $table->decimal('anteil_prozent', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['bankumsatz_id', 'kostenstelle_id'], 'bu_ks_unique');
        });

        Schema::create('beleg_kostenstelle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beleg_id')->constrained('belege')->cascadeOnDelete();
            $table->foreignId('kostenstelle_id')->constrained('kostenstellen')->cascadeOnDelete();
            $table->decimal('betrag', 14, 2)->nullable();
            $table->decimal('anteil_prozent', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['beleg_id', 'kostenstelle_id'], 'bel_ks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beleg_kostenstelle');
        Schema::dropIfExists('bankumsatz_kostenstelle');
    }
};
