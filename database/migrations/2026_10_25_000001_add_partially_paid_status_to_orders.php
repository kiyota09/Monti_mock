<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid')->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid')->change();
        });
    }

    public function down(): void
    {
        DB::table('purchase_orders')->where('payment_status', 'partially_paid')->update(['payment_status' => 'unpaid']);
        DB::table('sales_orders')->where('payment_status', 'partially_paid')->update(['payment_status' => 'unpaid']);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->change();
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->change();
        });
    }
};
