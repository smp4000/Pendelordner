<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankkonten (Modul 1/2). Mehrere Konten möglich, optional je Betrieb und
 * optional an einen FinTS-Zugang gekoppelt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('fints_connection_id')->nullable()->constrained('fints_connections')->nullOnDelete();

            $table->string('label');                  // Bezeichnung
            $table->string('bank_name')->nullable();
            $table->string('iban', 34)->nullable()->unique();
            $table->string('bic', 11)->nullable();
            $table->string('account_number')->nullable(); // Kontonummer
            $table->string('bank_code', 12)->nullable();  // BLZ
            $table->string('currency', 3)->default('EUR');

            $table->decimal('balance', 14, 2)->nullable();   // Saldo
            $table->timestamp('balance_date')->nullable();

            $table->boolean('fints_enabled')->default(false);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('color', 9)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
