<?php

namespace Tests\Feature;

use App\Models\Hrm\ApplicantJobApplication;
use App\Services\Hrm\ApplicantApplicationHoldService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicantApplicationHoldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('hrm_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
        });
        Schema::create('applicant_job_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('applicant_id');
            $table->unsignedBigInteger('job_posting_id')->nullable();
            $table->string('status', 30)->default('Submitted');
            $table->boolean('is_on_hold')->default(false);
            $table->string('previous_status', 30)->nullable();
            $table->text('hold_reason')->nullable();
            $table->unsignedBigInteger('held_by_application_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_onboarding_application_holds_every_other_application_and_keeps_its_own_stage(): void
    {
        $applicant = $this->applicant();
        $first = $this->application($applicant, 'Interviewed', 'Job A');
        $second = $this->application($applicant, 'Screening', 'Job B');
        $third = $this->application($applicant, 'Submitted', 'Job C');

        $first->update(['status' => 'Onboarding']);

        $this->assertFalse($first->fresh()->is_on_hold);
        foreach ([$second, $third] as $held) {
            $this->assertTrue($held->fresh()->is_on_hold);
            $this->assertSame($held->status, $held->fresh()->previous_status);
            $this->assertSame($first->id, $held->fresh()->held_by_application_id);
            $this->assertStringContainsString('Job A', $held->fresh()->hold_reason);
        }
        $this->assertSame(1, ApplicantApplicationHoldService::withoutHolds(ApplicantJobApplication::query())->count());
    }

    public function test_any_application_can_be_the_trigger_and_repeated_hold_does_not_overwrite_previous_stage(): void
    {
        $applicant = $this->applicant();
        $first = $this->application($applicant, 'Shortlisted', 'First');
        $second = $this->application($applicant, 'Interviewed', 'Second');

        $second->update(['status' => 'Onboarding']);
        ApplicantApplicationHoldService::holdSiblings($second->fresh());
        ApplicantApplicationHoldService::holdSiblings($second->fresh());

        $this->assertSame('Shortlisted', $first->fresh()->previous_status);
        $this->assertSame('Shortlisted', $first->fresh()->status);
        $this->assertSame($second->id, $first->fresh()->held_by_application_id);
    }

    public function test_rejected_onboarding_trigger_releases_holds_and_hire_closes_them(): void
    {
        $applicant = $this->applicant();
        $trigger = $this->application($applicant, 'Selected', 'Trigger');
        $sibling = $this->application($applicant, 'Interview Scheduled', 'Sibling');

        $trigger->update(['status' => 'Onboarding']);
        $trigger->update(['status' => 'Rejected']);
        $this->assertFalse($sibling->fresh()->is_on_hold);
        $this->assertSame('Interview Scheduled', $sibling->fresh()->status);

        $trigger->update(['status' => 'Onboarding']);
        $trigger->update(['status' => 'Hired']);
        $this->assertFalse($sibling->fresh()->is_on_hold);
        $this->assertSame('Withdrawn', $sibling->fresh()->status);
    }

    public function test_other_applicants_are_not_affected_and_new_applications_start_held_during_onboarding(): void
    {
        $applicant = $this->applicant();
        $otherApplicant = $this->applicant();
        $trigger = $this->application($applicant, 'Shortlisted', 'First');
        $unrelated = $this->application($otherApplicant, 'Interviewed', 'Other');

        $trigger->update(['status' => 'Onboarding']);
        $newApplication = $this->application($applicant, 'Submitted', 'New job');

        $this->assertTrue($newApplication->fresh()->is_on_hold);
        $this->assertSame($trigger->id, $newApplication->fresh()->held_by_application_id);
        $this->assertFalse($unrelated->fresh()->is_on_hold);
    }

    private function applicant(): int
    {
        return (int) \DB::table('applicants')->insertGetId([]);
    }

    private function application(int $applicantId, string $status, string $title): ApplicantJobApplication
    {
        $postingId = (int) \DB::table('hrm_job_postings')->insertGetId(['title' => $title]);

        return ApplicantJobApplication::create([
            'applicant_id' => $applicantId,
            'job_posting_id' => $postingId,
            'status' => $status,
        ]);
    }
}
