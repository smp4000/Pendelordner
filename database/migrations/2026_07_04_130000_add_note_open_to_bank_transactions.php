<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offener Hinweis pro Bankumsatz: markiert eine Mitteilung, auf die noch
 * reagiert werden muss (z. B. "Gutschrift angefordert"). Solche Hinweise
 * erscheinen im Dashboard-Widget "Offene Hinweise", bis sie als erledigt
 * bestätigt werden. Nur sinnvoll zusammen mit einer gesetzten accountant_note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->boolean('note_open')->default(false)->after('accountant_note')
                ->comment('Hinweis erfordert Reaktion (offenes To-Do)');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('note_open');
        });
    }
};
