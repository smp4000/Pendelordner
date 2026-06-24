<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung Beleg <-> Bankumsatz (Modul 5). n:m-Pivot mit Teilbetrag:
 * ein Umsatz kann mehrere Belege enthalten (z. B. Pappert 3.000 € =
 * 800 + 1.200 + 1.000), ein Beleg kann auf mehrere Umsätze aufgeteilt werden.
 * 'amount' = der diesem Umsatz zugeordnete Anteil des Belegs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_receipt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();

            $table->decimal('amount', 14, 2)->comment('dem Umsatz zugeordneter Anteil des Belegs');

            $table->string('match_type')->default('manual'); // manual|automatic|confirmed
            $table->decimal('match_score', 5, 2)->nullable()->comment('Trefferquote 0–100 %');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['bank_transaction_id', 'receipt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transaction_receipt');
    }
};
