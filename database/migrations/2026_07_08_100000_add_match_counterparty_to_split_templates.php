<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optionaler Empfänger-Auslöser für Aufteilungsvorlagen: enthält der Empfänger
 * (counterparty) eines Umsatzes diesen Text, wird die Vorlage beim Öffnen des
 * Aufteilungs-Editors automatisch geladen (z. B. "ARAL" -> Aral/OIL-Avis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('split_templates', function (Blueprint $table) {
            $table->string('match_counterparty')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('split_templates', function (Blueprint $table) {
            $table->dropColumn('match_counterparty');
        });
    }
};
