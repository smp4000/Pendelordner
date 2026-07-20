<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankleitzahl in die Bank-Vorlagen: bei bankspezifischen Vorlagen (z. B.
 * VR Bank Fulda) wird beim Auswählen auch die BLZ automatisch gesetzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_presets', function (Blueprint $table) {
            $table->string('bank_code')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('bank_presets', function (Blueprint $table) {
            $table->dropColumn('bank_code');
        });
    }
};
