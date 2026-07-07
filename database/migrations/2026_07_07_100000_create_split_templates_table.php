<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aufteilungsvorlagen: benannte Sätze fester Sachkonten (+ USt-Satz) für
 * wiederkehrende Aufteilungen wie das Aral/OIL-Avis. Beim Anwenden werden die
 * Zeilen (Konto + USt) vorbelegt; nur die Beträge trägt der Nutzer noch ein.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('split_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // Zeilen: [{ledger_number, tax_rate, label}, …]
            $table->json('rows');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_templates');
    }
};
