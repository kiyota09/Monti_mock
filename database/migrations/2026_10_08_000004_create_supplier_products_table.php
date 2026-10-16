<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplier product catalog: which Monti inventory materials each
     * supplier carries. Names/categories/units always come from the
     * materials table (single source of truth), so procurement can
     * match a material request to exactly the suppliers that stock it.
     */
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['supplier_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
