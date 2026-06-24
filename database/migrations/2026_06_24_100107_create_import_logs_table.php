<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import-Protokolle (Modul 1). Protokolliert jeden Bankabruf / Datei-Import
 * inkl. Dublettenstatistik. Quelle: fints | mt940 | camt | csv | manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->string('source')->index();          // Importquelle
            $table->string('filename')->nullable();

            $table->unsignedInteger('total_count')->default(0);     // gesamt
            $table->unsignedInteger('new_count')->default(0);       // neu importiert
            $table->unsignedInteger('duplicate_count')->default(0); // Dubletten
            $table->unsignedInteger('error_count')->default(0);     // Fehler

            $table->string('status')->default('running')->index();  // running|success|partial|error
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
