<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Freitext-Notiz/Hinweis je Steuerbüro-Dokument (erscheint als Info-Box im Bericht). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steuer_documents', function (Blueprint $table) {
            $table->text('note')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('steuer_documents', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
