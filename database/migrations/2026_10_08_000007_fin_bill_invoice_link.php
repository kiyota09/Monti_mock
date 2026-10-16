<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link finance bills to supplier purchase invoices so invoices sent
     * from the supplier portal flow into FIN Payables automatically.
     */
    public function up(): void
    {
        Schema::table('fin_bills', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_invoice_id')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('fin_bills', function (Blueprint $table) {
            $table->dropColumn('purchase_invoice_id');
        });
    }
};
