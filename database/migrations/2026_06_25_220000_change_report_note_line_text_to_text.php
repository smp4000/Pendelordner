<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweis-Zeilen-Text als TEXT (statt VARCHAR), damit mehrzeilige Memos mit
 * Zeilenumbrüchen ohne Längenbegrenzung gespeichert werden können (Modul 12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_note_lines', function (Blueprint $table) {
            $table->text('text')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_note_lines', function (Blueprint $table) {
            $table->string('text')->nullable()->change();
        });
    }
};
