<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eco_product_variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->string('color_group', 20);
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'material_id', 'color_group'], 'eco_product_variant_price_unique');
            $table->index(['product_id', 'color_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eco_product_variant_prices');
    }
};
