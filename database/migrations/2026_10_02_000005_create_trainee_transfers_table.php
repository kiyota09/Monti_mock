<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transfer history for failed-training actions. Snapshots are never
     * overwritten: from_* keeps the old position/department/result,
     * to_* carries the new assignment for the next training cycle.
     */
    public function up(): void
    {
        Schema::create('trainee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_department')->nullable();
            $table->string('from_position')->nullable();
            $table->string('to_department');
            $table->foreignId('to_department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->string('to_position');
            $table->foreignId('to_position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->date('effective_date');
            $table->text('reason');
            $table->boolean('new_training_required')->default(true);
            $table->string('training_result', 20)->default('Failed');
            $table->decimal('previous_grade', 5, 2)->nullable();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_transfers');
    }
};
