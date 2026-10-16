<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('recipe_id')->nullable()->after('design');
            $table->decimal('unit_price', 12, 2)->default(0)->after('quantity');
            $table->decimal('total_amount', 15, 2)->default(0)->after('unit_price');
            
            $table->foreign('recipe_id')->references('id')->on('bom_records')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropForeign(['recipe_id']);
            $table->dropColumn(['recipe_id', 'unit_price', 'total_amount']);
        });
    }
};