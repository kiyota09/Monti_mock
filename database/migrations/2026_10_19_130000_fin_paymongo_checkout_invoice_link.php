<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PayMongo checkout ledger also backs business-client (partner)
     * invoice payments, so a checkout row may target either a supplier bill
     * (FIN Payables) or a receivable invoice (partner portal). Make the bill
     * link optional and add the invoice link.
     */
    public function up(): void
    {
        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->dropForeign(['fin_bill_id']);
        });

        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->unsignedBigInteger('fin_bill_id')->nullable()->change();
            $table->unsignedBigInteger('fin_invoice_id')->nullable()->after('fin_bill_id');
        });

        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->foreign('fin_bill_id')->references('id')->on('fin_bills')->cascadeOnDelete();
            $table->foreign('fin_invoice_id')->references('id')->on('fin_invoices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->dropForeign(['fin_invoice_id']);
            $table->dropForeign(['fin_bill_id']);
        });

        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->dropColumn('fin_invoice_id');
            $table->unsignedBigInteger('fin_bill_id')->nullable(false)->change();
        });

        Schema::table('fin_paymongo_checkouts', function (Blueprint $table) {
            $table->foreign('fin_bill_id')->references('id')->on('fin_bills')->cascadeOnDelete();
        });
    }
};
