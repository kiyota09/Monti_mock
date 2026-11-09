<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_return_id')->nullable()->index();
        });

        Schema::table('order_returns', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('sales_order_id')->constrained('clients')->nullOnDelete();
            $table->string('request_type', 30)->nullable()->after('type');
            $table->json('package_ids')->nullable()->after('request_type');
            $table->string('image_path')->nullable()->after('reason');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('resolution_notes');
            $table->foreignId('replacement_sales_order_id')->nullable()->after('discount_amount')->constrained('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replacement_sales_order_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['request_type', 'package_ids', 'image_path', 'discount_amount']);
        });

        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropIndex(['replacement_return_id']);
            $table->dropColumn('replacement_return_id');
        });
    }
};
