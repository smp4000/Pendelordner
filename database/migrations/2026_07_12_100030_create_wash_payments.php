<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einzelne Wasch-Zahlungen aus dem Karten-/PayPal-Export (Modul Waschumsätze).
 *
 * Eine Zeile = eine Wäsche bzw. Abo-Zahlung. Station wird aus dem Text
 * ("… Fulda: …" / "… Petersberg: …") automatisch erkannt; "Subscription
 * payment" hat keine Station (business_id = null, bis manuell zugeordnet).
 * total = tatsächlich kassierter Bruttobetrag = Erlös; tax = enthaltene USt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wash_payments', function (Blueprint $table) {
            $table->id();
            // Vorgangs-Id aus dem Export -> Dublettenschutz bei Mehrfach-Import.
            $table->string('external_id');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            // card | paypal
            $table->string('payment_method')->default('card');
            $table->dateTime('created_source')->nullable();
            $table->date('payment_date');
            $table->string('customer_name')->nullable();
            $table->string('currency', 8)->default('eur');
            $table->decimal('subtotal', 8, 2)->default(0);   // Listenpreis
            $table->decimal('total', 8, 2)->default(0);       // tatsächlich kassiert (brutto)
            $table->decimal('tax', 8, 2)->default(0);         // enthaltene USt
            $table->decimal('discount', 8, 2)->default(0);    // Rabatt (negativ)
            $table->decimal('application_fee', 8, 2)->default(0);
            $table->decimal('surcharge', 8, 2)->nullable();
            $table->unsignedInteger('state_code')->nullable();
            $table->text('description')->nullable();
            $table->string('program')->nullable();            // Basis, …, Abo
            $table->string('plate')->nullable();
            $table->string('plate_normalized')->nullable();
            $table->boolean('is_subscription')->default(false);
            $table->boolean('is_free')->default(false);       // total == 0
            $table->timestamps();

            $table->unique('external_id', 'wash_pay_ext_uq');
            $table->index(['business_id', 'payment_date'], 'wash_pay_biz_date_idx');
            $table->index('plate_normalized', 'wash_pay_plate_idx');
            $table->index('state_code', 'wash_pay_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wash_payments');
    }
};
