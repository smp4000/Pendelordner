<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hinweise an das Steuerbüro (Modul 12): frei sortierbare Karten je Bankkonto
 * und Monat, die im Monatsbericht auf Seite 2 (unter der Zusammenfassung)
 * ausgegeben werden. Jede Karte hat eine Überschrift und mehrere Zeilen
 * (Betrag | Text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('period')->comment('Monat (erster Tag), auf den sich der Hinweis bezieht');
            $table->string('heading')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['bank_account_id', 'period']);
        });

        Schema::create('report_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_note_id')->constrained('report_notes')->cascadeOnDelete();
            $table->string('amount')->nullable()->comment('z. B. "120 € S"');
            $table->string('text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_note_lines');
        Schema::dropIfExists('report_notes');
    }
};
