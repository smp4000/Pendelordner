<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bedeutung der State-Codes aus dem Zahlungs-Export (Modul Waschumsätze).
 *
 * Der Export liefert nur Zahlen (7/8/9 …). Was sie bedeuten, ist anbieter-
 * abhängig und hier pflegbar: Label + ob der Vorgang als Umsatz zählt
 * (z. B. Storno/Erstattung = zählt nicht).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wash_payment_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();
            $table->string('label');
            $table->boolean('counts_as_revenue')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wash_payment_states');
    }
};
