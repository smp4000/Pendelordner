<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank-Vorlagen (Modul 1): vorbefüllte FinTS-Zugangsdaten je Bank (URL,
 * HBCI-Version) plus Hinweise zur Benutzerkennung/Kunden-ID/Kontonummer.
 * Erleichtert das Anlegen eines FinTS-Zugangs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Bankname (Anzeige)
            $table->string('fints_url');
            $table->string('hbci_version')->default('300');
            $table->string('login_hint')->nullable();        // Hinweis Benutzerkennung
            $table->string('customer_id_hint')->nullable();  // Hinweis Kunden-ID
            $table->string('account_hint')->nullable();      // Hinweis Kontonummer
            $table->text('note')->nullable();                // sonstige Hinweise
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_presets');
    }
};
