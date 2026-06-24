<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenstellen (Modul 9).
 *
 * Beispiele: Tankstelle, Shop, Lotto, Waschanlage, Werkstatt, Sachverständigenbüro.
 * Mehrfachzuordnung wird über die Pivot-Tabellen vorbereitet
 * (bankumsatz_kostenstelle / beleg_kostenstelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kostenstellen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();
            $table->string('nummer')->nullable()->comment('DATEV KOST1-Nummer');
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->string('farbe', 9)->nullable();
            $table->boolean('aktiv')->default(true)->index();
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kostenstellen');
    }
};
