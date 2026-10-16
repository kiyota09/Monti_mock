<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Official APPLICANTS portal (Dashboard/APPLICANTS/*) backend:
     * per-posting job applications, applicant notifications, and the
     * extended profile columns the portal Profile page edits.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('suffix')->nullable()->after('last_name');
            $table->string('barangay')->nullable()->after('street_address_line2');
            $table->string('highest_education')->nullable()->after('college_year');
            $table->string('school')->nullable()->after('highest_education');
            $table->string('course')->nullable()->after('school');
            $table->string('graduation_year', 10)->nullable()->after('course');
            $table->string('profile_photo_path')->nullable()->after('image');
            $table->timestamp('email_verified_at')->nullable()->after('email');
        });

        Schema::create('applicant_job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('hrm_job_postings')->nullOnDelete();
            $table->string('status', 30)->default('Submitted');
            $table->text('cover_letter')->nullable();
            $table->string('notice_period', 30)->nullable();
            $table->date('availability_date')->nullable();
            $table->string('resume_path')->nullable();
            $table->timestamps();
            $table->index(['applicant_id', 'job_posting_id']);
        });

        Schema::create('applicant_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('type', 30)->default('general'); // general|rejection|interview|onboarding
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['applicant_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_notifications');
        Schema::dropIfExists('applicant_job_applications');
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'suffix', 'barangay', 'highest_education', 'school', 'course',
                'graduation_year', 'profile_photo_path', 'email_verified_at',
            ]);
        });
    }
};
