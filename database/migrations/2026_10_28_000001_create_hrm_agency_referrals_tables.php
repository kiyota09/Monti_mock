<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_agency_referrals')) {
            Schema::create('hrm_agency_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_job_application_id')->constrained('applicant_job_applications')->cascadeOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->nullable()->constrained('hrm_job_postings')->nullOnDelete();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->string('status', 40)->default('Ready to Refer');
            $table->text('referral_reason');
            $table->text('agency_instructions')->nullable();
            $table->boolean('consent_verified')->default(false);
            $table->json('shared_documents')->nullable();
            $table->date('expected_response_date')->nullable();
            $table->date('referral_date');
            $table->timestamp('sent_at')->nullable();
            $table->text('agency_feedback')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->string('monti_decision', 40)->nullable();
            $table->text('decision_reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'referral_date']);
            $table->index(['applicant_id', 'job_posting_id']);
            });
        }

        if (! Schema::hasTable('hrm_agency_referral_histories')) {
            Schema::create('hrm_agency_referral_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_referral_id')->constrained('hrm_agency_referrals')->cascadeOnDelete();
            $table->string('action', 60);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
                $table->index(['agency_referral_id', 'created_at'], 'hrm_ref_hist_lookup_idx');
            });
        }

        // Keep this migration safe to retry if MySQL created the tables but
        // rejected an automatically generated index name on the first run.
        $hasHistoryIndex = collect(Schema::getIndexes('hrm_agency_referral_histories'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === 'hrm_ref_hist_lookup_idx');
        if (! $hasHistoryIndex) {
            Schema::table('hrm_agency_referral_histories', function (Blueprint $table) {
                $table->index(['agency_referral_id', 'created_at'], 'hrm_ref_hist_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_agency_referral_histories');
        Schema::dropIfExists('hrm_agency_referrals');
    }
};
