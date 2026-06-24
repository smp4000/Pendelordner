<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinTS-Zugänge (Modul 1). Ein Online-Banking-Login (= FinTS-Zugang) kann
 * mehrere Bankkonten umfassen. Die PIN wird verschlüsselt gespeichert
 * (encrypted-Cast im Model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fints_connections', function (Blueprint $table) {
            $table->id();
            $table->string('label')->comment('Bezeichnung, z. B. "VR Bank Fulda"');
            $table->string('bank_code', 12)->comment('Bankleitzahl (BLZ)');
            $table->string('fints_url');
            $table->string('hbci_version')->default('300');
            $table->string('username')->comment('Benutzerkennung');
            $table->text('pin')->nullable()->comment('verschlüsselt (encrypted cast)');
            $table->string('tan_method')->nullable();   // TAN-Verfahren
            $table->string('tan_medium')->nullable();

            $table->string('product_id')->nullable();   // FinTS-Produktregistrierung
            $table->string('product_version')->default('1.0');

            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fints_connections');
    }
};
