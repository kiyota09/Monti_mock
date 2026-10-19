<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervisor → staff job assignments.
 *
 * A supervisor receives a Job Order (ManufacturingOrder) in their department and
 * assigns it to a specific staff member for a specific stage, preventing two
 * people from accidentally working the same JO. The row is the handoff record
 * between departments (knitting → dyeing → finishing → logistics).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('man_task_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('manufacturing_order_id')
                ->nullable()
                ->constrained('manufacturing_orders')
                ->nullOnDelete();
            $table->foreignId('sales_order_id')
                ->nullable()
                ->constrained('sales_orders')
                ->nullOnDelete();
            $table->foreignId('fabric_id')
                ->nullable()
                ->constrained('fabrics')
                ->nullOnDelete();

            $table->enum('department', ['knitting', 'dyeing', 'finishing']);
            $table->string('stage');                 // knitting, dyeing_color, ... finishing_packaging, quality_check, logistics
            $table->string('assigned_role');         // manufacturing_role slug that performs the work
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])
                ->default('pending');
            $table->unsignedInteger('sequence')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['manufacturing_order_id', 'department']);
            $table->index(['department', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('man_task_assignments');
    }
};
