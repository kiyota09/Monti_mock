<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_requests', function (Blueprint $table) {
            $table->id();
            $table->string('req_number')->unique();                     // MR-2026-001
            $table->foreignId('material_id')->constrained('materials'); // link to materials table
            $table->string('material_name');
            $table->string('category');
            $table->string('unit');
            $table->decimal('current_stock', 12, 2)->default(0);
            $table->decimal('reorder_point', 12, 2)->default(0);
            $table->decimal('required_qty', 12, 2);
            $table->enum('urgency', ['High', 'Medium', 'Low'])->default('Medium');
            $table->string('requested_by')->default('Inventory System');
            $table->date('requested_at');
            $table->enum('status', [
                'pending',
                'rfq_sent',
                'po_created',
                'fulfilled',
                'cancelled',
            ])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'urgency']);
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_requests');
    }
};
