<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->string('receipt_file')->nullable();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->string('receipt_file')->nullable();
        });
    }

    public function down()
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'receipt_file']);
        });
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'receipt_file']);
        });
    }
};