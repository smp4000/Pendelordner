<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Vordefinierte Hinweis-Texte für Steuerbüro-Dokumente (per Auswahlbox, erweiterbar). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steuer_note_texts', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steuer_note_texts');
    }
};
