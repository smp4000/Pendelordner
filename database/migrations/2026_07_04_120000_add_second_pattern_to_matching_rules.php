<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweites (optionales) Kriterium für Zuordnungsregeln. Nötig, wenn mehrere
 * Verträge derselben Gesellschaft abgebucht werden (z. B. AXA mit mehreren
 * Vertragsnummern): Empfänger allein greift zu breit, daher zusätzlich ein
 * zweiter Suchbegriff (etwa die Vertrags-/Mandatsnummer im Verwendungszweck).
 * Beide Kriterien werden mit UND verknüpft; jedes für sich ist eine
 * "enthält"-Prüfung (LIKE, ohne Groß-/Kleinschreibung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_rules', function (Blueprint $table) {
            $table->string('pattern2')->nullable()->after('pattern_type')
                ->comment('Optionaler zweiter Suchbegriff (UND-Verknüpfung)');
            $table->string('pattern_type2')->nullable()->after('pattern2'); // counterparty|purpose|iban|any
        });
    }

    public function down(): void
    {
        Schema::table('matching_rules', function (Blueprint $table) {
            $table->dropColumn(['pattern2', 'pattern_type2']);
        });
    }
};
