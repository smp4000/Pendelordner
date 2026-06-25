<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sachkonten / Kontenrahmen (Modul 13). Importiert aus den edtas-Kontenplänen
 * (edtas allgemein, Kfz-Handel, Gastronomie). Dienen zur Kontierung von Belegen
 * und Umsätzen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('chart')->index();          // edtas | kfz | gastro
            $table->string('number')->index();         // Kontonummer
            $table->string('name');                    // Kontobezeichnung
            $table->string('group')->nullable();       // Zuordnung GA (z. B. "B, Personalkosten")
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['chart', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
