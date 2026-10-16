<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expanded agency worker profile (Sections A-F):
     * personal contacts, resume/qualifications, work/deployment,
     * government docs, emergency relationship, submission consent.
     * Supporting files live in worker_documents with HR verification
     * states: Pending | Verified | Rejected | Expired.
     */
    public function up(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            // A. Personal Information
            $table->string('email')->nullable()->after('phone');
            // B. Resume and Qualifications
            $table->string('resume_path')->nullable()->after('email');
            $table->string('resume_original_name')->nullable()->after('resume_path');
            $table->text('education')->nullable()->after('resume_original_name');
            $table->text('work_experience')->nullable()->after('education');
            $table->text('skills')->nullable()->after('work_experience');
            $table->text('trainings_certifications')->nullable()->after('skills');
            // C. Work and Deployment Details
            $table->decimal('years_experience', 5, 1)->nullable()->after('position_skill');
            $table->string('proposed_department')->nullable()->after('years_experience');
            $table->string('availability')->nullable()->after('proposed_department');
            $table->string('employment_arrangement')->nullable()->after('availability');
            // D. Government and Supporting Documents
            $table->string('gov_id_type', 60)->nullable()->after('employment_arrangement');
            // E. Emergency Contact
            $table->string('emergency_relationship', 60)->nullable()->after('emergency_name');
            // F. Submission and Consent
            $table->boolean('consent_given')->default(false)->after('pending_note');
            $table->timestamp('consent_at')->nullable()->after('consent_given');
            $table->string('privacy_notice_version', 60)->nullable()->after('consent_at');
        });

        Schema::create('worker_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('workers')->cascadeOnDelete();
            // resume | gov_id | clearance | medical | training_cert | other
            $table->string('doc_type', 40);
            $table->string('label')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->date('expiry_date')->nullable();
            // Pending | Verified | Rejected | Expired
            $table->string('status', 20)->default('Pending');
            $table->text('review_note')->nullable();
            $table->string('verified_by_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['worker_id', 'status']);
            $table->index(['worker_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_documents');

        Schema::table('workers', function (Blueprint $table) {
            $table->dropColumn([
                'email',
                'resume_path',
                'resume_original_name',
                'education',
                'work_experience',
                'skills',
                'trainings_certifications',
                'years_experience',
                'proposed_department',
                'availability',
                'employment_arrangement',
                'gov_id_type',
                'emergency_relationship',
                'consent_given',
                'consent_at',
                'privacy_notice_version',
            ]);
        });
    }
};
