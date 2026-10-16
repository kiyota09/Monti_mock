<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HRM_NEW onboarding: templates + items + activity definitions,
     * onboarding instances + item snapshots + activities + notes.
     */
    public function up(): void
    {
        Schema::create('hrm_onboarding_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->constrained('hrm_employment_types')->nullOnDelete();
            $table->string('work_mode', 20)->nullable();
            $table->string('status', 20)->default('Draft'); // Draft|Active|Inactive|Archived
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_onboarding_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('hrm_onboarding_templates')->cascadeOnDelete();
            $table->string('category', 60)->default('Applicant Requirements');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('input_type', 40)->default('file');
            $table->string('responsible_party', 60)->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('applicant_visible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('due_rule_type', 40)->default('none');
            $table->string('due_rule_value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hrm_onboarding_template_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('hrm_onboarding_templates')->cascadeOnDelete();
            $table->string('type', 60)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('default_method', 20)->default('On-site');
            $table->unsignedInteger('default_duration_minutes')->default(60);
            $table->boolean('is_required')->default(true);
            $table->boolean('applicant_visible')->default(true);
            $table->boolean('attendance_required')->default(false);
            $table->boolean('must_complete_before_onboarding_completion')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('hrm_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('hrm_onboarding_templates')->nullOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('hrm_job_postings')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('work_mode', 20)->nullable();
            $table->string('work_location')->nullable();
            $table->text('work_schedule')->nullable();
            $table->string('salary_range')->nullable();
            $table->date('start_date')->nullable();
            $table->date('expected_start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->string('assigned_manager')->nullable();
            $table->string('status', 40)->default('In Progress');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('hrm_onboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hrm_onboardings')->cascadeOnDelete();
            $table->foreignId('template_item_id')->nullable()->constrained('hrm_onboarding_template_items')->nullOnDelete();
            $table->string('name');
            $table->string('status', 30)->default('Pending');
            $table->date('due_date')->nullable();
            $table->string('file_url')->nullable();
            $table->string('original_name')->nullable();
            $table->text('value')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_onboarding_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hrm_onboardings')->cascadeOnDelete();
            $table->foreignId('template_activity_id')->nullable()->constrained('hrm_onboarding_template_activities')->nullOnDelete();
            $table->string('title');
            $table->string('type', 60)->nullable();
            $table->text('description')->nullable();
            $table->date('schedule_date')->nullable();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('method', 20)->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('facilitator')->nullable();
            $table->string('organizer')->nullable();
            $table->string('status', 30)->default('Pending');
            $table->string('attendance_status', 30)->default('Pending');
            $table->boolean('is_required')->default(true);
            $table->boolean('applicant_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_onboarding_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('hrm_onboardings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->boolean('is_hr_private')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_onboarding_notes');
        Schema::dropIfExists('hrm_onboarding_activities');
        Schema::dropIfExists('hrm_onboarding_items');
        Schema::dropIfExists('hrm_onboardings');
        Schema::dropIfExists('hrm_onboarding_template_activities');
        Schema::dropIfExists('hrm_onboarding_template_items');
        Schema::dropIfExists('hrm_onboarding_templates');
    }
};
