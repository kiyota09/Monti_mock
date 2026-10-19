<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PayMongo hosted-checkout attempts for supplier bills (FIN · Payables).
     * One row per "Pay with PayMongo" click. The row is created before the
     * browser is redirected to the gateway and is completed on the return
     * URL (and/or webhook) once PayMongo confirms the payment, so the ledger
     * payment is only written after a verified charge.
     */
    public function up(): void
    {
        Schema::create('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('fin_bill_id')->constrained('fin_bills')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending')->index(); // pending | paid | failed | expired
            $table->string('method')->nullable();
            $table->string('paymongo_session_id')->nullable()->unique();
            $table->string('checkout_url', 512)->nullable();
            $table->string('gateway_reference')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_paymongo_checkouts');
    }
};
