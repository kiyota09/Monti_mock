<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bom', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Optional FK to materials — allows a BOM line to reference master material
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('sku_ref')->nullable();       // display SKU / mat_id
            $table->string('name');
            $table->decimal('qty', 10, 4)->default(1);
            $table->string('unit');
            $table->string('category')->nullable();
            $table->string('warehouse_note')->nullable(); // which warehouse the material lives in
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('stock_status')->default('In Stock');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bom');
    }
};
