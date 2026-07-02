<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inhaltliche Beleg-Dubletten (gleiche Rechnung, andere Datei): der neue Beleg
 * wird isoliert und verweist auf das Original, bis der Nutzer entscheidet
 * (löschen oder behalten). Exakte Datei-Dubletten werden bereits beim Upload
 * über den Datei-Hash übersprungen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('duplicate_of_id')->nullable()
                ->constrained('receipts', indexName: 'receipts_dup_of_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
        });
    }
};
