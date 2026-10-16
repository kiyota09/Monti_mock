<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HRM_NEW recruitment: job postings, application screenings,
     * interviews + evaluations. Links to legacy `applicants` table
     * (kept as canonical applicant master) via nullable FKs.
     */
    public function up(): void
    {
        Schema::create('hrm_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('posting_id', 40)->unique();
            $table->string('title');
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->string('department')->nullable();
            $table->string('reports_to')->nullable();
            $table->foreignId('employment_type_id')->nullable()->constrained('hrm_employment_types')->nullOnDelete();
            $table->unsignedInteger('vacancies')->default(1);
            $table->unsignedInteger('filled_vacancies')->default(0);
            $table->integer('salary_min')->nullable();
            $table->integer('salary_max')->nullable();
            $table->string('salary_visibility', 40)->default('Show Range');
            $table->string('hiring_priority', 20)->default('Normal'); // Normal|High|Urgent
            $table->string('status', 20)->default('Draft'); // Draft|Published|Closed|Archived
            $table->string('recruiter', 100)->nullable();
            $table->string('hiring_manager', 100)->nullable();
            $table->string('work_location')->nullable();
            $table->string('work_mode', 20)->default('On-site');
            $table->text('work_schedule')->nullable();
            $table->text('recruitment_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('require_initial_interview')->default(true);
            $table->boolean('require_final_interview')->default(false);
            $table->date('closing_date')->nullable();
            $table->date('expected_start_date')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'archived_at']);
        });

        Schema::create('hrm_application_screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('hrm_job_postings')->nullOnDelete();
            $table->boolean('resume_reviewed')->default(false);
            $table->boolean('education_verified')->default(false);
            $table->boolean('work_experience_reviewed')->default(false);
            $table->boolean('skills_reviewed')->default(false);
            $table->boolean('qualifications_met')->default(false);
            $table->boolean('applicant_info_reviewed')->default(false);
            $table->string('result', 20)->default('pending'); // passed|rejected|pending
            $table->text('notes')->nullable();
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('hrm_job_postings')->nullOnDelete();
            $table->string('candidate_name')->nullable();
            $table->string('position')->nullable();
            $table->string('type', 20)->default('Initial'); // Initial|Final
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('start_time', 10)->nullable();
            $table->string('end_time', 10)->nullable();
            $table->string('method', 20)->default('Onsite'); // Online|Onsite
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('Scheduled'); // Scheduled|Completed|Cancelled|No Show
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'date']);
        });

        Schema::create('hrm_interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained('hrm_interviews')->cascadeOnDelete();
            $table->unsignedTinyInteger('communication_skills')->nullable();
            $table->unsignedTinyInteger('relevant_skills')->nullable();
            $table->unsignedTinyInteger('technical_knowledge')->nullable();
            $table->unsignedTinyInteger('problem_solving')->nullable();
            $table->unsignedTinyInteger('work_experience')->nullable();
            $table->unsignedTinyInteger('adaptability')->nullable();
            $table->unsignedTinyInteger('teamwork')->nullable();
            $table->unsignedTinyInteger('professionalism')->nullable();
            $table->text('strengths')->nullable();
            $table->text('concerns')->nullable();
            $table->text('interview_notes')->nullable();
            $table->string('result', 30)->default('pending_review'); // passed|failed|pending_review
            $table->string('recommendation', 60)->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_interview_evaluations');
        Schema::dropIfExists('hrm_interviews');
        Schema::dropIfExists('hrm_application_screenings');
        Schema::dropIfExists('hrm_job_postings');
    }
};
