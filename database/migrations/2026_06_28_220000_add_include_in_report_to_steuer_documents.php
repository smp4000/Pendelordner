<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schalter je Steuerbüro-Dokument: im Bericht drucken (einbetten) oder nur speichern. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steuer_documents', function (Blueprint $table) {
            $table->boolean('include_in_report')->default(true)->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('steuer_documents', function (Blueprint $table) {
            $table->dropColumn('include_in_report');
        });
    }
};
