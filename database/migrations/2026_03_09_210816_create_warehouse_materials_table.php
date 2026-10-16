<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_materials');
    }
};
