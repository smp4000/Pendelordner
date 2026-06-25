<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpfung Tankstelle (Betrieb) ↔ Lieferant mit Kundennummer (Modul 4).
 *
 * n:m-Beziehung: Eine Tankstelle kann viele Lieferanten haben, ein Lieferant
 * viele Tankstellen. Je Paar wird die Kundennummer hinterlegt, die der
 * Lieferant dieser Tankstelle zugeteilt hat. Über mehrere Tankstellen desselben
 * Lieferanten darf die Kundennummer gleich oder unterschiedlich sein.
 * Dient der automatischen Lieferanten-/Tankstellen-Erkennung aus Rechnungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_customer_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('customer_number')->nullable()->index(); // Kundennummer beim Lieferanten
            $table->text('note')->nullable();
            $table->timestamps();

            // Eine Tankstelle hat je Lieferant in der Regel genau eine Kundennummer.
            $table->unique(['supplier_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_customer_numbers');
    }
};
