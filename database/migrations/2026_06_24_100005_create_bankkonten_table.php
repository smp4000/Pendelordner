<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankkonten (Modul 1 / 2).
 *
 * Mehrere Bankkonten möglich (VR Bank Fulda, Geschäftskonto Werkstatt, ...).
 * Optional einem Betrieb zugeordnet und optional an einen FinTS-Zugang gekoppelt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankkonten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();
            $table->foreignId('fints_zugang_id')->nullable()->constrained('fints_zugaenge')->nullOnDelete();

            $table->string('bezeichnung');
            $table->string('bank_name')->nullable();
            $table->string('iban', 34)->nullable()->unique();
            $table->string('bic', 11)->nullable();
            $table->string('kontonummer')->nullable();
            $table->string('blz', 12)->nullable();
            $table->string('waehrung', 3)->default('EUR');

            $table->decimal('saldo', 14, 2)->nullable();
            $table->timestamp('saldo_datum')->nullable();

            $table->boolean('fints_aktiv')->default(false);
            $table->boolean('aktiv')->default(true)->index();
            $table->timestamp('letzter_abruf_at')->nullable();
            $table->string('farbe', 9)->nullable();
            $table->text('notiz')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankkonten');
    }
};
