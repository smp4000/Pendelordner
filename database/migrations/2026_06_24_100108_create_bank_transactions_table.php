<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankumsätze (Modul 1/2) – zentrale Tabelle.
 * Dublettenprüfung über 'dedup_hash' (Referenz + Datum + Betrag + Zweck).
 * Status: open | partially_allocated | fully_allocated | reviewed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('import_log_id')->nullable()->constrained('import_logs')->nullOnDelete();

            $table->string('bank_reference')->nullable()->comment('Bankreferenz / End-to-End-ID');
            $table->date('booking_date')->index();        // Buchungsdatum
            $table->date('value_date')->nullable();       // Valutadatum

            $table->string('counterparty')->nullable()->comment('Empfänger / Auftraggeber');
            $table->string('counterparty_iban', 34)->nullable();
            $table->string('counterparty_bic', 11)->nullable();
            $table->text('purpose')->nullable();          // Verwendungszweck

            $table->decimal('amount', 14, 2)->comment('negativ = Ausgang, positiv = Eingang');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('balance_after', 14, 2)->nullable();

            $table->string('transaction_code', 10)->nullable()->comment('GVC – Geschäftsvorfallcode');
            $table->string('booking_text')->nullable()->comment('Buchungsart laut Bank');

            $table->string('status')->default('open')->index();
            $table->boolean('reviewed')->default(false)->index();   // geprüft
            $table->boolean('fully_paid')->default(false);          // vollständig bezahlt
            $table->text('note')->nullable();

            $table->string('import_source')->default('manual');     // Importquelle
            $table->string('dedup_hash', 64)->index()->comment('SHA-256 für Dublettenprüfung');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bank_account_id', 'dedup_hash'], 'bank_transaction_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
