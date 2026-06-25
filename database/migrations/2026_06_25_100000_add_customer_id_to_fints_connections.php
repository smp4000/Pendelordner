<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ergänzt die Kunden-ID (z. B. VR-Kennung bei Volksbanken). Die meisten Banken
 * benötigen sie nicht – php-fints ermittelt sie i. d. R. automatisch. Das Feld
 * dient der Dokumentation und optionalen Verwendung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fints_connections', function (Blueprint $table) {
            $table->string('customer_id')->nullable()->after('username')
                ->comment('Kunden-ID / VR-Kennung (optional, meist automatisch ermittelt)');
        });
    }

    public function down(): void
    {
        Schema::table('fints_connections', function (Blueprint $table) {
            $table->dropColumn('customer_id');
        });
    }
};
