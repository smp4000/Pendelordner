<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorien (Modul 8).
 *
 * Standardkategorien: Blumen, Backwaren, Shop, Getränke, Lotto, Kraftstoffe,
 * Waschanlage, Reparaturen, Werkzeug, Telefon, Internet, Strom, Wasser,
 * Versicherung, Miete, Marketing, Fahrzeuge, Bürobedarf, Sachverständigenkosten.
 *
 * Enthält bereits Default-Kontierung (SKR03/04) zur Vorbereitung der Buchhaltung
 * (Modul 13). Benutzer kann Kategorien erweitern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategorien', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('kategorien')->nullOnDelete();
            $table->string('name');
            $table->text('beschreibung')->nullable();
            $table->string('farbe', 9)->nullable();
            $table->string('icon')->nullable();

            // Default-Kontierung (Vorbereitung Modul 13)
            $table->string('skr03_konto')->nullable();
            $table->string('skr04_konto')->nullable();
            $table->string('steuerschluessel')->nullable()->comment('DATEV-BU-Schlüssel, z. B. 9 = 19% VSt');
            $table->decimal('standard_steuersatz', 5, 2)->nullable();

            $table->boolean('aktiv')->default(true)->index();
            $table->unsignedInteger('sortierung')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategorien');
    }
};
