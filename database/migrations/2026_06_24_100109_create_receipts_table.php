<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Belege / Belegarchiv (Modul 3). Upload von PDF/JPG/PNG/TIFF, OCR-Erkennung
 * von Lieferant, Rechnungsnummer, Datum, Beträgen, Steuer und IBAN.
 * Typ: incoming_invoice | outgoing_invoice | cash | other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();

            $table->string('type')->default('incoming_invoice')->index(); // Belegart
            $table->string('receipt_number')->nullable()->comment('interne fortlaufende Belegnummer');
            $table->string('invoice_number')->nullable();   // Rechnungsnummer

            $table->date('invoice_date')->nullable()->index(); // Rechnungsdatum
            $table->date('service_date')->nullable();          // Leistungsdatum
            $table->date('due_date')->nullable();              // fällig am

            $table->decimal('gross_amount', 14, 2)->nullable(); // Brutto
            $table->decimal('net_amount', 14, 2)->nullable();   // Netto
            $table->decimal('tax_amount', 14, 2)->nullable();   // Steuerbetrag
            $table->decimal('tax_rate', 5, 2)->nullable();      // Steuersatz
            $table->string('currency', 3)->default('EUR');
            $table->string('iban', 34)->nullable();

            // Datei
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable()->index()->comment('SHA-256 Dateidubletten');

            // OCR
            $table->longText('ocr_text')->nullable();
            $table->string('ocr_status')->default('pending')->index();
            $table->timestamp('ocr_processed_at')->nullable();

            $table->string('status')->default('new')->index();
            $table->boolean('paid')->default(false)->index();   // bezahlt
            $table->boolean('reviewed')->default(false);        // geprüft
            $table->text('note')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
