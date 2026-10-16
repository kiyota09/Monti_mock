<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Creates the main table once
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('po_number')->unique();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('status')->default('credit_review');
            $table->string('tier_level')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Creates the items table
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Drop in reverse order to respect foreign keys
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};
