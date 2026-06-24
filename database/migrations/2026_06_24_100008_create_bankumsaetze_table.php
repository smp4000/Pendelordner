<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bankumsätze (Modul 1 / 2) – zentrale Tabelle.
 *
 * Dublettenprüfung über 'dedup_hash' (Referenz + Datum + Betrag + Verwendungszweck).
 * Status: offen | teilweise_zugeordnet | vollstaendig_zugeordnet | geprueft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bankumsaetze', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bankkonto_id')->constrained('bankkonten')->cascadeOnDelete();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();
            $table->foreignId('kategorie_id')->nullable()->constrained('kategorien')->nullOnDelete();
            $table->foreignId('kostenstelle_id')->nullable()->constrained('kostenstellen')->nullOnDelete();
            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->foreignId('import_protokoll_id')->nullable()->constrained('import_protokolle')->nullOnDelete();

            $table->string('bank_referenz')->nullable()->comment('Bankreferenz / End-to-End-ID');
            $table->date('buchungsdatum')->index();
            $table->date('valutadatum')->nullable();

            $table->string('empfaenger')->nullable()->comment('Empfänger / Auftraggeber');
            $table->string('empfaenger_iban', 34)->nullable();
            $table->string('empfaenger_bic', 11)->nullable();
            $table->text('verwendungszweck')->nullable();

            $table->decimal('betrag', 14, 2)->comment('negativ = Ausgang, positiv = Eingang');
            $table->string('waehrung', 3)->default('EUR');
            $table->decimal('saldo_nach', 14, 2)->nullable();

            $table->string('gvc_code', 10)->nullable()->comment('Geschäftsvorfallcode');
            $table->string('buchungstext')->nullable()->comment('Buchungsart laut Bank');

            // offen | teilweise_zugeordnet | vollstaendig_zugeordnet | geprueft
            $table->string('status')->default('offen')->index();
            $table->boolean('geprueft')->default(false)->index();
            $table->boolean('vollstaendig_bezahlt')->default(false);
            $table->text('notiz')->nullable();

            // fints | mt940 | camt | csv | manuell
            $table->string('import_quelle')->default('manuell');
            $table->string('dedup_hash', 64)->index()->comment('SHA-256 für Dublettenprüfung');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bankkonto_id', 'dedup_hash'], 'bankumsatz_dedup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bankumsaetze');
    }
};
