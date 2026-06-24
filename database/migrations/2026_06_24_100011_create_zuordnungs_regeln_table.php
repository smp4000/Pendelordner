<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnungsregeln (Modul 4 – lernfähige Auto-Zuordnung).
 *
 * Beispiele: HBW = Blumen, Pappert = Backwaren, Telekom = Telefon,
 * VR-Pay = Kartenzahlungen, Aral = Tankstelle.
 *
 * 'treffer_anzahl' wird bei jeder Bestätigung erhöht -> lernfähig: häufig
 * bestätigte Regeln gewinnen an Priorität.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zuordnungs_regeln', function (Blueprint $table) {
            $table->id();
            $table->string('muster')->comment('Suchbegriff, z. B. "HBW", "PAPPERT", "TELEKOM"');
            // empfaenger | verwendungszweck | iban | beliebig
            $table->string('muster_typ')->default('empfaenger');

            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->foreignId('kategorie_id')->nullable()->constrained('kategorien')->nullOnDelete();
            $table->foreignId('kostenstelle_id')->nullable()->constrained('kostenstellen')->nullOnDelete();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();

            // Vorgeschlagene Kontierung (Modul 13)
            $table->string('skr03_konto')->nullable();
            $table->string('skr04_konto')->nullable();
            $table->string('steuerschluessel')->nullable();

            $table->unsignedInteger('prioritaet')->default(0)->index();
            $table->unsignedInteger('treffer_anzahl')->default(0)->comment('Lernzähler');
            $table->boolean('aktiv')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zuordnungs_regeln');
    }
};
