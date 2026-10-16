<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * APPLICANTS module, connected to HRM_NEW.
     * Links legacy `applicants` rows to the DB-rooted HRM foundation
     * (job postings, departments, org positions, employment types) and
     * adds document + status-history children. All FKs nullable for BC.
     */
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->foreignId('job_posting_id')->nullable()->after('position_applied')
                ->constrained('hrm_job_postings')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('job_posting_id')
                ->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('org_position_id')->nullable()->after('department_id')
                ->constrained('hrm_positions')->nullOnDelete();
            $table->foreignId('employment_type_id')->nullable()->after('org_position_id')
                ->constrained('hrm_employment_types')->nullOnDelete();
            $table->foreignId('assigned_hr_id')->nullable()->after('assigned_module')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('hired_user_id')->nullable()->after('assigned_hr_id')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('applicant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('type', 60); // resume, id, sss, philhealth, pagibig, certificate, other
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->boolean('verified')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['applicant_id', 'type']);
        });

        Schema::create('applicant_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index('applicant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_status_histories');
        Schema::dropIfExists('applicant_documents');
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hired_user_id');
            $table->dropConstrainedForeignId('assigned_hr_id');
            $table->dropConstrainedForeignId('employment_type_id');
            $table->dropConstrainedForeignId('org_position_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('job_posting_id');
        });
    }
};
