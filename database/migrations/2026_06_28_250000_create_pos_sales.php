<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kassenumsätze je Tankstelle und Monat (aus der Aral-Kassenabrechnung):
 * eine Zeile je Artikelgruppe mit Menge, Bruttobetrag und EKW-Konto.
 * Kraftstoffzeilen führen die Menge in Litern (is_fuel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->date('period')->comment('Monat (erster Tag)');
            $table->unsignedInteger('fn')->default(0);
            $table->string('article_group');
            $table->decimal('quantity', 14, 3)->default(0)->comment('Menge (Kraftstoff: Liter)');
            $table->decimal('amount_gross', 14, 2)->default(0)->comment('Bruttobetrag EUR');
            $table->string('ekw_konto')->nullable();
            $table->boolean('is_fuel')->default(false);
            $table->timestamps();

            $table->index(['business_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sales');
    }
};
