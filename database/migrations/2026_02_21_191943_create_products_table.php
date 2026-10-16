<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_id')->unique(); // Auto-generated
            $table->string('sku')->unique(); // Auto-generated
            $table->string('name');
            $table->string('category')->default('Uncategorized');
            $table->string('status')->default('Active');
            $table->string('color_name')->nullable();
            $table->string('color_hex')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};