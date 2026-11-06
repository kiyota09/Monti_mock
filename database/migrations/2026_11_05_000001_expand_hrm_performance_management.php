<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('hrm_kpi_templates')) {
            Schema::create('hrm_kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->string('evaluation_type', 24)->default('regular');
            $table->text('description')->nullable();
            $table->string('frequency', 24)->default('quarterly');
            $table->date('effective_date')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
                $table->index(['department_id', 'position_id', 'evaluation_type'], 'hrm_kpi_template_match_idx');
            });
        }

        Schema::table('hrm_probation_evaluations', function (Blueprint $table) {
            $table->string('evaluation_type', 24)->default('probationary')->index();
            $table->foreignId('template_id')->nullable()->constrained('hrm_kpi_templates')->nullOnDelete();
            $table->json('template_snapshot')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employment_status_snapshot')->nullable();
            $table->string('employment_type_snapshot')->nullable();
            $table->string('classification', 40)->nullable();
            $table->string('hr_recommendation', 60)->nullable();
            $table->string('management_decision', 60)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('employee_feedback')->nullable();
        });

        Schema::create('hrm_kpi_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('hrm_kpi_templates')->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('measurement_method')->nullable();
            $table->decimal('weight', 5, 2);
            $table->text('performance_standards')->nullable();
            $table->text('required_evidence')->nullable();
            $table->json('rating_guidelines')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hrm_evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('hrm_probation_evaluations')->cascadeOnDelete();
            $table->unsignedBigInteger('criterion_id')->nullable();
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->text('comments')->nullable();
            $table->json('evidence')->nullable();
            $table->json('objective_data')->nullable();
            $table->timestamps();
            $table->index(['evaluation_id', 'criterion_id']);
        });

        Schema::create('hrm_evaluation_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('hrm_probation_evaluations')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['evaluation_id', 'created_at']);
        });

        Schema::create('hrm_performance_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('hrm_probation_evaluations')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('identified_issues');
            $table->text('objectives');
            $table->text('standards')->nullable();
            $table->text('training_or_coaching')->nullable();
            $table->date('start_date');
            $table->date('target_date');
            $table->json('progress_notes')->nullable();
            $table->text('review_results')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('hrm_probation_evaluations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['evaluation_type', 'template_snapshot', 'employment_status_snapshot', 'employment_type_snapshot', 'classification', 'hr_recommendation', 'management_decision', 'acknowledged_at', 'employee_feedback']);
        });
        Schema::dropIfExists('hrm_performance_improvement_plans');
        Schema::dropIfExists('hrm_evaluation_audit_logs');
        Schema::dropIfExists('hrm_evaluation_criteria');
        Schema::dropIfExists('hrm_kpi_criteria');
        Schema::dropIfExists('hrm_kpi_templates');
    }
};
