<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same-job 6-month re-application cooldown for interview/training
     * failures: when an applicant fails an Initial/Final interview (or
     * training) for a job, the rejected application row is stamped with
     * the stage + timestamp so the portal can refuse re-application to
     * that same job until 6 months have passed. Other jobs stay open.
     */
    public function up(): void
    {
        Schema::table('applicant_job_applications', function (Blueprint $table) {
            $table->string('failed_stage', 30)->nullable()->after('status');
            $table->timestamp('failed_at')->nullable()->after('failed_stage');
            $table->text('rejection_reason')->nullable()->after('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('applicant_job_applications', function (Blueprint $table) {
            $table->dropColumn(['failed_stage', 'failed_at', 'rejection_reason']);
        });
    }
};
