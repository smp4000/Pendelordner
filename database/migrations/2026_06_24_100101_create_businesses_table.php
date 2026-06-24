<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betriebe / Tankstellen (Modul 7).
 * Beispiele: Aral Petersberg, weitere Tankstelle, Werkstatt, Sachverständigenbüro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Anzeigename des Betriebs
            $table->string('short_name')->nullable();     // Kurzname
            $table->string('type')->default('gas_station')->index(); // Betriebsart
            $table->string('street')->nullable();         // Straße
            $table->string('postal_code', 10)->nullable();// PLZ
            $table->string('city')->nullable();           // Ort
            $table->string('tax_number')->nullable();     // Steuernummer
            $table->string('vat_id')->nullable();         // USt-IdNr.
            $table->string('color', 9)->nullable();       // Farbe für UI
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('note')->nullable();             // Notiz
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
