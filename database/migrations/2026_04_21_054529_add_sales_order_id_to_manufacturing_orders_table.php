<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            // Make purchase_order_id nullable first (needs to drop foreign key if exists)
            $table->dropForeign(['purchase_order_id']); // adjust foreign key name if needed
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();

            // Add sales_order_id column
            $table->unsignedBigInteger('sales_order_id')->nullable()->after('purchase_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders');
        });
    }
};