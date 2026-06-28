<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Felder der zentralen Eingabe-Maske (Stufe 3) nach Vorbild des Excel-Blatts
 * „EINGABE": Allgemein, Art der Planung, Unternehmerdaten, Tankstellendaten
 * sowie Waschgeschäft/Kfz. Diese Angaben beschreiben die Station und steuern
 * (in einem weiteren Schritt) automatische Berechnungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            // Allgemein / Art der Planung
            $table->unsignedTinyInteger('plan_start_month')->default(1)->after('year_to')
                ->comment('Planung ab Monat (1–12), anteiliges erstes Jahr');
            $table->boolean('neugruendung')->default(false)->after('plan_start_month');

            // Unternehmerdaten
            $table->string('mineraloel')->nullable()->default('Aral - Tankstelle')->after('city');
            $table->string('inhaber')->nullable()->after('mineraloel');
            $table->string('bundesland')->nullable()->after('inhaber');
            $table->string('telefon')->nullable()->after('bundesland');
            $table->string('email')->nullable()->after('telefon');
            $table->string('unternehmensform')->nullable()->default('Einzelunternehmen')->after('email');
            $table->string('mehrfachbetreiber')->nullable()->after('unternehmensform');

            // Tankstellendaten
            $table->boolean('backshop')->default(false);
            $table->boolean('gastronomie')->default(false);
            $table->decimal('verderb_backshop_pct', 5, 2)->default(0);
            $table->decimal('verderb_gastro_pct', 5, 2)->default(0);
            $table->boolean('pfandschlupf')->default(false);
            $table->boolean('kaffeeautomat')->default(false);
            $table->string('kaffeekonzept')->nullable();
            $table->unsignedInteger('anzahl_terminal')->default(0);
            $table->boolean('mautstation')->default(false);
            $table->boolean('nebengeschaefte')->default(false);
            $table->string('nebengeschaeft1')->nullable();
            $table->string('nebengeschaeft2')->nullable();
            $table->boolean('unternehmer_pkw')->default(false);
            $table->decimal('bruttolistenpreis', 12, 2)->default(0);
            $table->boolean('digitale_buchhaltung')->default(false);
            $table->boolean('mandant_contax')->default(false);
            $table->boolean('verfahrensdoku')->default(false);

            // Waschgeschäft / Kfz-Dienstleistungen
            $table->boolean('werkstatt')->default(false);
            $table->boolean('muenzgeraete')->default(false);
            $table->boolean('waschanlage')->default(false);
            $table->boolean('wasseraufbereitung')->default(false);
            $table->unsignedInteger('anzahl_waeschen')->default(0);
            $table->decimal('waschpreis', 8, 2)->default(0);
            $table->unsignedInteger('anzahl_waeschen_1')->default(0);
            $table->decimal('waschpreis_1', 8, 2)->default(0);
            $table->unsignedInteger('anzahl_waeschen_2')->default(0);
            $table->decimal('waschpreis_2', 8, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('business_plans', function (Blueprint $table) {
            $table->dropColumn([
                'plan_start_month', 'neugruendung',
                'mineraloel', 'inhaber', 'bundesland', 'telefon', 'email', 'unternehmensform', 'mehrfachbetreiber',
                'backshop', 'gastronomie', 'verderb_backshop_pct', 'verderb_gastro_pct', 'pfandschlupf',
                'kaffeeautomat', 'kaffeekonzept', 'anzahl_terminal', 'mautstation', 'nebengeschaefte',
                'nebengeschaeft1', 'nebengeschaeft2', 'unternehmer_pkw', 'bruttolistenpreis',
                'digitale_buchhaltung', 'mandant_contax', 'verfahrensdoku',
                'werkstatt', 'muenzgeraete', 'waschanlage', 'wasseraufbereitung',
                'anzahl_waeschen', 'waschpreis', 'anzahl_waeschen_1', 'waschpreis_1', 'anzahl_waeschen_2', 'waschpreis_2',
            ]);
        });
    }
};
