<?php

namespace Tests\Feature;

use App\Models\Hrm\Applicant;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ApplicantLoginFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->string('password')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state_province')->nullable();
            $table->string('postal_zip_code')->nullable();
            $table->string('position_applied')->nullable();
            $table->string('status')->default('Submitted');
            $table->boolean('archived')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        // Shared Inertia props query these tables on every page render.
        Schema::create('page_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->string('page');
            $table->string('permission_level')->nullable();
            $table->timestamps();
        });
        Schema::create('workforce_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->timestamps();
        });
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('module');
            $table->timestamps();
        });
        // Tables touched by the applicant profile payload (minimal stubs).
        Schema::create('hrm_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('status')->default('Draft');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('hrm_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('hrm_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        Schema::create('hrm_employment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
        foreach (['applicant_documents', 'applicant_status_histories', 'hrm_application_screenings', 'hrm_interview_evaluations', 'interviews'] as $t) {
            Schema::create($t, function (Blueprint $table) {
                $table->id();
                $table->foreignId('applicant_id')->nullable();
                $table->foreignId('interview_id')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('hrm_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable();
            $table->string('status')->default('Scheduled');
            $table->timestamps();
        });
        Schema::create('applicant_job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable();
            $table->foreignId('job_posting_id')->nullable();
            $table->string('status')->default('Submitted');
            $table->timestamps();
        });
    }

    public function test_applicant_login_lands_in_applicant_portal(): void
    {
        $applicant = Applicant::create([
            'first_name' => 'Flow',
            'last_name' => 'Test',
            'email' => 'flow@test.com',
            'phone_number' => '+630000000000',
            'password' => 'secret123',
            'position_applied' => 'Tester',
            'status' => 'Submitted',
            'archived' => false,
        ]);

        $login = $this->post(route('applicant.login.store'), [
            'email' => 'flow@test.com',
            'password' => 'secret123',
        ]);

        $login->assertRedirect(route('applicant.dashboard'));
        $this->assertAuthenticatedAs($applicant, 'applicant');

        $dash = $this->actingAs($applicant, 'applicant')->get(route('applicant.dashboard'));
        $dash->assertOk();
    }

    public function test_guest_hitting_portal_is_not_sent_to_employee_login(): void
    {
        $res = $this->get(route('applicant.dashboard'));
        $res->assertRedirect(route('applicant.login'));
    }

    public function test_stale_employee_intended_url_does_not_hijack_applicant_login(): void
    {
        Applicant::create([
            'first_name' => 'Flow',
            'last_name' => 'Test',
            'email' => 'flow@test.com',
            'password' => 'secret123',
            'status' => 'Submitted',
            'archived' => false,
        ]);

        // Simulate an earlier employee-portal bounce stored in session.
        $login = $this->withSession(['url.intended' => route('dashboard')])
            ->post(route('applicant.login.store'), [
                'email' => 'flow@test.com',
                'password' => 'secret123',
            ]);

        $login->assertRedirect(route('applicant.dashboard'));
    }
}
