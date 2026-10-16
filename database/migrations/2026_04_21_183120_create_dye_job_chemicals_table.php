<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores each individual chemical/dye lot consumed in a dye job.
     * Mirrors the knitting yarn approach: one row per inventory lot consumed.
     * quantity_used is in kg (same unit as manufacturing_inventory_items.remaining_quantity).
     */
    public function up(): void
    {
        Schema::create('dye_job_chemicals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('dye_job_id');
            $table->foreign('dye_job_id')
                ->references('id')
                ->on('dye_jobs')
                ->onDelete('cascade');

            $table->unsignedBigInteger('inventory_item_id');
            $table->foreign('inventory_item_id')
                ->references('id')
                ->on('manufacturing_inventory_items')
                ->onDelete('restrict');

            // Denormalised for fast display (no extra join when reading job details)
            $table->string('dye_type');          // material name
            $table->string('control_number');    // inventory control number

            $table->decimal('quantity_used', 10, 2);   // kg consumed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dye_job_chemicals');
    }
};