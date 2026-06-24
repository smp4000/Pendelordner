<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betriebe / Tankstellen (Modul 7).
 *
 * Jeder Datensatz (Bankumsatz, Beleg) kann einem Betrieb zugeordnet werden.
 * Beispiele: Aral Petersberg, weitere Tankstelle, Werkstatt, Sachverständigenbüro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('betriebe', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kurzname')->nullable();
            // tankstelle | werkstatt | sachverstaendigenbuero | shop | sonstige
            $table->string('typ')->default('tankstelle')->index();
            $table->string('strasse')->nullable();
            $table->string('plz', 10)->nullable();
            $table->string('ort')->nullable();
            $table->string('steuernummer')->nullable();
            $table->string('ust_id')->nullable();
            $table->string('farbe', 9)->nullable(); // Hex-Farbe für UI-Kennzeichnung
            $table->boolean('aktiv')->default(true)->index();
            $table->unsignedInteger('sortierung')->default(0);
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('betriebe');
    }
};
