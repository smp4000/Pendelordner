<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontierungen (Modul 13 – Vorbereitung, noch keine vollständige Buchung).
 * Polymorph zu Bankumsatz ODER Beleg. Hält Konto/Gegenkonto, Steuerschlüssel,
 * Kostenstelle, Buchungstext etc. für SKR03/SKR04.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_assignments', function (Blueprint $table) {
            $table->id();

            // Polymorph: assignable_type / assignable_id (Bankumsatz oder Beleg)
            $table->morphs('assignable');

            $table->string('chart_of_accounts')->default('skr03'); // skr03|skr04|other
            $table->string('account')->nullable();      // Konto
            $table->string('contra_account')->nullable(); // Gegenkonto
            $table->string('tax_key')->nullable()->comment('DATEV-BU-Schlüssel');

            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->string('cost_center_2')->nullable()->comment('KOST2');

            $table->string('document_number')->nullable(); // Belegnummer
            $table->string('booking_text')->nullable();    // Buchungstext
            $table->date('service_date')->nullable();      // Leistungsdatum
            $table->date('booking_date')->nullable();      // Buchungsdatum
            $table->decimal('amount', 14, 2)->nullable();

            $table->boolean('exported')->default(false)->index();
            $table->foreignId('datev_export_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_assignments');
    }
};
