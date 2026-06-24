<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lieferanten / Kreditoren (Modul 4/13/14). Stammdaten für die automatische
 * Zuordnung und für die DATEV-Vorbereitung. Default-Felder beschleunigen die
 * Erfassung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable(); // Anzeigename

            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('default_cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('default_business_id')->nullable()->constrained('businesses')->nullOnDelete();

            $table->string('iban', 34)->nullable()->index();
            $table->string('bic', 11)->nullable();
            $table->string('vat_id')->nullable();       // USt-IdNr.
            $table->string('tax_number')->nullable();   // Steuernummer

            // DATEV-Vorbereitung
            $table->string('creditor_number')->nullable(); // Kreditor
            $table->string('debtor_number')->nullable();   // Debitor

            // Default-Kontierung
            $table->string('skr03_account')->nullable();
            $table->string('skr04_account')->nullable();
            $table->string('tax_key')->nullable();

            $table->string('street')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();

            $table->boolean('active')->default(true)->index();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
