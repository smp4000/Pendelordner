<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merker "Aufteilung noch offen": Ein Umsatz kann als geprüft/bezahlt in den
 * Bericht aufgenommen werden, während die Aufteilung (Split) auf Sachkonten
 * noch fehlt. Solche Umsätze erscheinen im Dashboard-Widget "Offene
 * Aufteilungen" mit dem noch offenen Restbetrag, bis die Aufteilung
 * vollständig ist (Rest 0) oder der Merker manuell entfernt wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->boolean('split_open')->default(false)->after('note_open')
                ->comment('Aufteilung noch zu ergänzen (offenes To-Do)');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('split_open');
        });
    }
};
