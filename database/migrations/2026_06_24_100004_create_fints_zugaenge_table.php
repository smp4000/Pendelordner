<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FinTS-Zugänge (Modul 1 – Bankanbindung).
 *
 * Ein FinTS-Zugang (= Online-Banking-Login einer Bank) kann mehrere Bankkonten
 * umfassen. Zugangsdaten (PIN) werden über das Eloquent-'encrypted'-Cast
 * verschlüsselt in der DB abgelegt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fints_zugaenge', function (Blueprint $table) {
            $table->id();
            $table->string('bezeichnung')->comment('z. B. "VR Bank Fulda"');
            $table->string('bank_code', 12)->comment('Bankleitzahl (BLZ)');
            $table->string('fints_url');
            $table->string('hbci_version')->default('300');
            $table->string('benutzerkennung');
            $table->text('pin')->nullable()->comment('verschlüsselt (encrypted cast)');
            $table->string('tan_verfahren')->nullable();
            $table->string('tan_medium')->nullable();

            // FinTS-Produktregistrierung (DK-Registrierung)
            $table->string('produkt_id')->nullable();
            $table->string('produkt_version')->default('1.0');

            $table->boolean('aktiv')->default(true)->index();
            $table->timestamp('letzter_abruf_at')->nullable();
            $table->text('letzte_meldung')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fints_zugaenge');
    }
};
