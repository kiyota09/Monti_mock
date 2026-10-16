<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HRM_NEW training & development tables.
     * Attendance reuses canonical `attendance_logs` (ClockController).
     */
    public function up(): void
    {
        Schema::create('hrm_trainings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('In Progress'); // In Progress|Completed|Draft
            $table->unsignedInteger('duration_hours')->default(1);
            $table->unsignedInteger('expiry_days')->nullable(); // cert validity window
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('hrm_trainings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('Enrolled'); // Enrolled|In Progress|Completed
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['training_id', 'user_id']);
        });

        Schema::create('hrm_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_id')->nullable()->constrained('hrm_trainings')->nullOnDelete();
            $table->string('name');
            $table->date('issued_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_certifications');
        Schema::dropIfExists('hrm_training_enrollments');
        Schema::dropIfExists('hrm_trainings');
    }
};
