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
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            
            // Link to the main Purchase Order
            $table->foreignId('purchase_order_id')
                  ->constrained()
                  ->onDelete('cascade');

            // J.O. Number: Shared by all colors in one batch (e.g., JO-20260409-ABCD)
            $table->string('jo_number'); 

            // Control Number: Unique per specific color row (e.g., CTL-26PO123-1)
            $table->string('control_number')->unique(); 

            // Production Details
            $table->string('yarn_type')->nullable();
            $table->string('design')->nullable();
            $table->string('color');
            
            // Measurement & Requirements
            $table->decimal('quantity', 10, 2);
            $table->text('description')->nullable();

            // Status tracking for production stages
            $table->string('status')->default('pending'); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};