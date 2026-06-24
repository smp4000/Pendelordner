<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belege / Belegarchiv (Modul 3).
 *
 * Upload von PDF/JPG/PNG/TIFF, OCR-Erkennung von Lieferant, Rechnungsnummer,
 * Datum, Beträgen, Steuer und IBAN. Typ entspricht den Upload-Tabs:
 * rechnungseingang | rechnungsausgang | kasse | sonstige.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('belege', function (Blueprint $table) {
            $table->id();
            $table->foreignId('betrieb_id')->nullable()->constrained('betriebe')->nullOnDelete();
            $table->foreignId('lieferant_id')->nullable()->constrained('lieferanten')->nullOnDelete();
            $table->foreignId('kategorie_id')->nullable()->constrained('kategorien')->nullOnDelete();
            $table->foreignId('kostenstelle_id')->nullable()->constrained('kostenstellen')->nullOnDelete();

            $table->string('typ')->default('rechnungseingang')->index();
            $table->string('beleg_nummer')->nullable()->comment('interne fortlaufende Belegnummer');
            $table->string('rechnungsnummer')->nullable();

            $table->date('rechnungsdatum')->nullable()->index();
            $table->date('leistungsdatum')->nullable();
            $table->date('faellig_am')->nullable();

            $table->decimal('betrag_brutto', 14, 2)->nullable();
            $table->decimal('betrag_netto', 14, 2)->nullable();
            $table->decimal('steuerbetrag', 14, 2)->nullable();
            $table->decimal('steuersatz', 5, 2)->nullable();
            $table->string('waehrung', 3)->default('EUR');
            $table->string('iban', 34)->nullable();

            // Datei
            $table->string('datei_pfad')->nullable();
            $table->string('datei_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('datei_groesse')->nullable();
            $table->string('datei_hash', 64)->nullable()->index()->comment('SHA-256 zur Dateidubletten-Erkennung');

            // OCR
            $table->longText('ocr_text')->nullable();
            // ausstehend | verarbeitet | fehler | uebersprungen
            $table->string('ocr_status')->default('ausstehend')->index();
            $table->timestamp('ocr_durchgefuehrt_at')->nullable();

            // neu | zugeordnet | bezahlt | geprueft
            $table->string('status')->default('neu')->index();
            $table->boolean('bezahlt')->default(false)->index();
            $table->boolean('geprueft')->default(false);
            $table->text('notiz')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('belege');
    }
};
