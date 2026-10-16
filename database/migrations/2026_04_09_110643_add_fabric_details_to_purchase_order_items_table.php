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
        Schema::table('purchase_order_items', function (Blueprint $table) {
        $table->string('fabric')->nullable()->after('product_id');
        $table->string('design')->nullable()->after('fabric');
        $table->string('color')->nullable()->after('design');
        $table->decimal('kilos', 10, 2)->default(0)->after('color');
        $table->decimal('price', 15, 2)->default(0)->after('kilos');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            //
        });
    }
};
