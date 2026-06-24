<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mehrfach-Kostenstellen-Zuordnung (Modul 9 – "Mehrfachzuordnung vorbereiten").
 * Erlaubt die anteilige Verteilung eines Bankumsatzes bzw. Belegs auf mehrere
 * Kostenstellen. Im einfachen Fall genügt cost_center_id am Hauptdatensatz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transaction_cost_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('bank_transactions')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['bank_transaction_id', 'cost_center_id'], 'bt_cc_unique');
        });

        Schema::create('cost_center_receipt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->cascadeOnDelete();
            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['receipt_id', 'cost_center_id'], 'cc_receipt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_center_receipt');
        Schema::dropIfExists('bank_transaction_cost_center');
    }
};
