<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waschprogramme als Kassen-Artikel je Betrieb (Modul Waschumsätze).
 *
 * Bildet die App-Programmnamen (Basis, Schnell, Rundum, Glanz, Hochglanz,
 * Cabrio, Abo) auf die Kassen-Artikel ab. Gleiche Bezeichnung/Preise an beiden
 * Stationen, aber je Station EIGENE EAN – daher je (Betrieb, Programm) ein
 * Datensatz. Voll pflegbar (EAN, Preis, Konto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wash_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            // Programm-Token, wie es im Zahlungstext steht (Basis, Schnell, …, Abo).
            $table->string('program');
            // Kassen-Bezeichnung, z. B. "Hochglanzwaesche".
            $table->string('name');
            // einzel | flatrate
            $table->string('type')->default('einzel');
            // Kassen-Artikelnummer (z. B. 623839) – optional.
            $table->string('article_number')->nullable();
            $table->string('ean')->nullable();
            // Kassen-Verkaufspreis (VK) brutto.
            $table->decimal('price', 8, 2)->nullable();
            // eDTAS-Erlöskonto (Standard 6621 Erlöse Wagenpflege, USt voll).
            $table->string('ledger_account')->default('6621');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Kurzer Unique-Name (MySQL 64-Zeichen-Limit).
            $table->unique(['business_id', 'program'], 'wash_art_biz_prog_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wash_articles');
    }
};
