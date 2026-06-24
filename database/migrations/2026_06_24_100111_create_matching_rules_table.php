<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnungsregeln (Modul 4 – lernfähige Auto-Zuordnung). Beispiele:
 * HBW = Blumen, Pappert = Backwaren, Telekom = Telefon, VR-Pay =
 * Kartenzahlungen, Aral = Tankstelle. 'hit_count' steigt bei jeder Bestätigung
 * -> häufig genutzte Regeln gewinnen an Priorität.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('pattern')->comment('Suchbegriff, z. B. "HBW", "PAPPERT"');
            $table->string('pattern_type')->default('counterparty'); // counterparty|purpose|iban|any

            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();

            // Vorgeschlagene Kontierung
            $table->string('skr03_account')->nullable();
            $table->string('skr04_account')->nullable();
            $table->string('tax_key')->nullable();

            $table->unsignedInteger('priority')->default(0)->index();
            $table->unsignedInteger('hit_count')->default(0)->comment('Lernzähler');
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rules');
    }
};
