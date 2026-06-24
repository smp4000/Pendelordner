<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten / Kreditoren (Modul 4 / 13 / 14).
 *
 * Dient als Stammdaten für die automatische Zuordnung und als Kreditor für die
 * spätere DATEV-Vorbereitung. Default-Kategorie/-Kostenstelle/-Kontierung
 * beschleunigen die Erfassung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieferanten', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('anzeigename')->nullable();

            $table->foreignId('standard_kategorie_id')->nullable()->constrained('kategorien')->nullOnDelete();
            $table->foreignId('standard_kostenstelle_id')->nullable()->constrained('kostenstellen')->nullOnDelete();
            $table->foreignId('standard_betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();

            $table->string('iban', 34)->nullable()->index();
            $table->string('bic', 11)->nullable();
            $table->string('ust_id')->nullable();
            $table->string('steuernummer')->nullable();

            // DATEV-Vorbereitung (Modul 14)
            $table->string('kreditor_nummer')->nullable();
            $table->string('debitor_nummer')->nullable();

            // Default-Kontierung (Modul 13)
            $table->string('skr03_konto')->nullable();
            $table->string('skr04_konto')->nullable();
            $table->string('steuerschluessel')->nullable();

            $table->string('strasse')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();

            $table->boolean('aktiv')->default(true)->index();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieferanten');
    }
};
