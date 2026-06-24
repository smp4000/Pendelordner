<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kostenstellen (Modul 9). Beispiele: Tankstelle, Shop, Lotto, Waschanlage,
 * Werkstatt, Sachverständigenbüro. Mehrfachzuordnung über Pivot-Tabellen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('number')->nullable()->comment('DATEV KOST1-Nummer');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 9)->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
