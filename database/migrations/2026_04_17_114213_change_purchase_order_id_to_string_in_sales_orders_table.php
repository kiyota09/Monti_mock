<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('sales_orders', function (Blueprint $table) {
        // Change the column to string to accept "PO-2026-001"
        $table->string('purchase_order_id')->change();
    });
}

public function down(): void
{
    Schema::table('sales_orders', function (Blueprint $table) {
        $table->unsignedBigInteger('purchase_order_id')->change();
    });
}
};
