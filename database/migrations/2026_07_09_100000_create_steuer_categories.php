<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frei erweiterbare Kategorien für Steuerbüro-PDFs (z. B. Monatsrechnung,
 * Kontoauszug, Kundenrechnung). Werden als Dropdown zur Auswahl angeboten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steuer_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steuer_categories');
    }
};
