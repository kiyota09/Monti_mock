<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('warehouse_stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('control_number')->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shelf_id')->nullable()->constrained('warehouse_shelves')->nullOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->string('unit');
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users');
            $table->foreignId('purchase_order_id')->nullable()->constrained('scm_purchase_orders')->nullOnDelete();
            $table->enum('status', ['in_stock', 'reserved', 'used', 'rejected'])->default('in_stock');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_stock_items');
    }
};