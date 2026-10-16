<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->after('purchase_order_id');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->onDelete('cascade');
            // Make purchase_order_id nullable because a queue can now belong to either a PO or a Sales Order
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('order_queues', function (Blueprint $table) {
            $table->dropForeign(['sales_order_id']);
            $table->dropColumn('sales_order_id');
            $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
        });
    }
};