<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(12)->after('discount_amount');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->default(0)->after('unit_price');
            $table->decimal('tax_rate', 5, 2)->default(12)->after('subtotal');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_rate', 'tax_amount']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }
};
