<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->change();
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->decimal('total_quantity', 12, 2)->change();
            $table->decimal('remaining_quantity', 12, 2)->default(0)->change();
        });

        Schema::table('warehouse_packages', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_packages', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            $table->integer('total_quantity')->change();
            $table->integer('remaining_quantity')->default(0)->change();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });
    }
};
