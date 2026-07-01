<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belege/Dateien zu den Steuerbüro-Hinweisen: je Bankkonto (Tankstelle),
 * Monat und Kategorie (z. B. Monatsrechnung) hochgeladene PDFs. Sie werden im
 * Monatsbericht vor den Kontoauszug-Belegen einsortiert und je Monat ab 1
 * nummeriert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steuer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('period')->comment('Monat (erster Tag)');
            $table->string('category')->default('Monatsrechnung');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['bank_account_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steuer_documents');
    }
};
