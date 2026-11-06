<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('fabric_type')->nullable()->after('fabric');
            $table->foreignId('eco_quotation_item_id')->nullable()->after('product_id')
                ->constrained('eco_quotation_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eco_quotation_item_id');
            $table->dropColumn('fabric_type');
        });
    }
};
