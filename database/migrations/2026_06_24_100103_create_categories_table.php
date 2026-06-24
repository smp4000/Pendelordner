<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategorien (Modul 8) inkl. Default-Kontierung SKR03/SKR04 (Vorbereitung
 * Modul 13). Standardkategorien: Blumen, Backwaren, Shop, Getränke, Lotto,
 * Kraftstoffe, Waschanlage, Reparaturen, Werkzeug, Telefon, Internet, Strom,
 * Wasser, Versicherung, Miete, Marketing, Fahrzeuge, Bürobedarf,
 * Sachverständigenkosten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 9)->nullable();
            $table->string('icon')->nullable();

            // Default-Kontierung (Vorbereitung Buchhaltung)
            $table->string('skr03_account')->nullable();  // SKR03-Konto
            $table->string('skr04_account')->nullable();  // SKR04-Konto
            $table->string('tax_key')->nullable()->comment('DATEV-BU-Schlüssel, z. B. 9 = 19% VSt');
            $table->decimal('default_tax_rate', 5, 2)->nullable(); // Standard-Steuersatz

            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
