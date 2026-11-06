<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hrm_kpi_templates', 'template_code')) {
            Schema::table('hrm_kpi_templates', function (Blueprint $table) {
                $table->string('template_code', 40)->nullable()->index('hrm_kpi_template_code_idx');
            });
        }
        if (! Schema::hasColumn('hrm_kpi_templates', 'rating_scale')) {
            Schema::table('hrm_kpi_templates', function (Blueprint $table) {
                $table->json('rating_scale')->nullable();
            });
        }

        Schema::create('hrm_kpi_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('hrm_kpi_templates')->restrictOnDelete();
            $table->unsignedInteger('template_version');
            $table->json('template_snapshot');
            $table->json('classification_snapshot');
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date')->nullable();
            $table->text('assignment_notes')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->string('classification', 60)->nullable();
            $table->text('evaluator_comments')->nullable();
            $table->text('strengths')->nullable();
            $table->text('development_recommendations')->nullable();
            $table->string('recommendation', 80)->nullable();
            $table->text('manager_comments')->nullable();
            $table->text('hr_comments')->nullable();
            $table->string('status', 32)->default('Assigned')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('employee_feedback')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'period_start', 'period_end'], 'kpi_eval_employee_period_idx');
            $table->index(['manager_id', 'status'], 'kpi_eval_manager_status_idx');
            $table->index(['evaluator_id', 'status'], 'kpi_eval_evaluator_status_idx');
        });

        Schema::create('hrm_kpi_evaluation_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('hrm_kpi_evaluations')->cascadeOnDelete();
            $table->unsignedBigInteger('criterion_id')->nullable();
            $table->string('category', 120);
            $table->string('criterion_name', 180);
            $table->text('description')->nullable();
            $table->text('performance_standards')->nullable();
            $table->text('required_evidence')->nullable();
            $table->json('rating_guidelines')->nullable();
            $table->decimal('weight', 5, 2);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->decimal('weighted_score', 6, 2)->nullable();
            $table->text('comments')->nullable();
            $table->json('objective_data')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['evaluation_id', 'criterion_id'], 'kpi_rating_eval_criterion_idx');
        });

        Schema::create('hrm_kpi_evaluation_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_rating_id')->constrained('hrm_kpi_evaluation_ratings')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_kpi_evaluation_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('hrm_kpi_evaluations')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('review_role', 20);
            $table->string('decision', 32);
            $table->text('comments')->nullable();
            $table->string('previous_status', 32);
            $table->string('new_status', 32);
            $table->timestamps();
            $table->index(['evaluation_id', 'created_at'], 'kpi_review_eval_date_idx');
        });

        Schema::create('hrm_kpi_employee_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->unique()->constrained('hrm_kpi_evaluations')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('agrees')->nullable();
            $table->text('comments')->nullable();
            $table->timestamp('acknowledged_at');
            $table->timestamps();
        });

        Schema::create('hrm_kpi_pips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('evaluation_id')->nullable()->constrained('hrm_kpi_evaluations')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('performance_gap');
            $table->text('improvement_objective');
            $table->text('required_actions');
            $table->text('training_or_coaching')->nullable();
            $table->text('success_measurement');
            $table->text('notes')->nullable();
            $table->date('start_date');
            $table->date('target_date');
            $table->date('review_date')->nullable();
            $table->text('review_results')->nullable();
            $table->string('status', 24)->default('Draft')->index();
            $table->timestamps();
            $table->index(['user_id', 'status'], 'kpi_pip_employee_status_idx');
        });

        Schema::create('hrm_kpi_pip_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pip_id')->constrained('hrm_kpi_pips')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('progress_note');
            $table->date('recorded_on');
            $table->timestamps();
        });

        Schema::create('hrm_kpi_classification_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('version')->default(1);
            $table->decimal('minimum_score', 5, 2);
            $table->decimal('maximum_score', 5, 2);
            $table->string('label', 60);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->index(['version', 'minimum_score'], 'kpi_classification_version_idx');
        });

        Schema::create('hrm_kpi_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->nullable()->constrained('hrm_kpi_evaluations')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 48);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['evaluation_id', 'created_at'], 'kpi_audit_eval_date_idx');
        });

        if (DB::table('hrm_kpi_classification_rules')->count() === 0) {
            $now = now();
            DB::table('hrm_kpi_classification_rules')->insert([
                ['version' => 1, 'minimum_score' => 90, 'maximum_score' => 100, 'label' => 'Outstanding', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['version' => 1, 'minimum_score' => 80, 'maximum_score' => 90, 'label' => 'Exceeds Expectations', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['version' => 1, 'minimum_score' => 70, 'maximum_score' => 80, 'label' => 'Meets Expectations', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['version' => 1, 'minimum_score' => 60, 'maximum_score' => 70, 'label' => 'Needs Improvement', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['version' => 1, 'minimum_score' => 0, 'maximum_score' => 60, 'label' => 'Unsatisfactory', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_kpi_audit_logs');
        Schema::dropIfExists('hrm_kpi_classification_rules');
        Schema::dropIfExists('hrm_kpi_pip_progress');
        Schema::dropIfExists('hrm_kpi_pips');
        Schema::dropIfExists('hrm_kpi_employee_acknowledgments');
        Schema::dropIfExists('hrm_kpi_evaluation_reviews');
        Schema::dropIfExists('hrm_kpi_evaluation_evidence');
        Schema::dropIfExists('hrm_kpi_evaluation_ratings');
        Schema::dropIfExists('hrm_kpi_evaluations');
        if (Schema::hasColumn('hrm_kpi_templates', 'template_code')) {
            Schema::table('hrm_kpi_templates', function (Blueprint $table) {
                $table->dropIndex('hrm_kpi_template_code_idx');
                $table->dropColumn('template_code');
            });
        }
        if (Schema::hasColumn('hrm_kpi_templates', 'rating_scale')) {
            Schema::table('hrm_kpi_templates', function (Blueprint $table) {
                $table->dropColumn('rating_scale');
            });
        }
    }
};
