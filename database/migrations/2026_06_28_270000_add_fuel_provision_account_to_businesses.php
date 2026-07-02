<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** eDTAS-Konto für die Kraftstoff-Provision (Erlös-Verbuchung der Kassenumsätze). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('fuel_provision_account')->nullable()->comment('eDTAS-Konto Kraftstoff-Provision');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('fuel_provision_account');
        });
    }
};
