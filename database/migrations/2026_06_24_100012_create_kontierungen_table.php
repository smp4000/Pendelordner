<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontierungen (Modul 13 – Vorbereitung Buchhaltung, noch keine vollständige Buchung).
 *
 * Polymorphe Zuordnung zu Bankumsatz ODER Beleg. Hält Konto/Gegenkonto,
 * Steuerschlüssel, Kostenstelle, Buchungstext etc. für SKR03/SKR04.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontierungen', function (Blueprint $table) {
            $table->id();

            // Polymorph: kontierbar_type / kontierbar_id (Bankumsatz oder Beleg)
            $table->morphs('kontierbar');

            // skr03 | skr04 | sonstige
            $table->string('kontenrahmen')->default('skr03');
            $table->string('konto')->nullable();
            $table->string('gegenkonto')->nullable();
            $table->string('steuerschluessel')->nullable()->comment('DATEV-BU-Schlüssel');

            $table->foreignId('kostenstelle_id')->nullable()->constrained('kostenstellen')->nullOnDelete();
            $table->string('kost2')->nullable()->comment('zweite Kostenstelle (KOST2)');

            $table->string('belegnummer')->nullable();
            $table->string('buchungstext')->nullable();
            $table->date('leistungsdatum')->nullable();
            $table->date('buchungsdatum')->nullable();
            $table->decimal('betrag', 14, 2)->nullable();

            $table->boolean('exportiert')->default(false)->index();
            $table->foreignId('datev_export_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontierungen');
    }
};
