<?php

namespace Tests\Feature;

use App\Models\Core\User;
use App\Models\Hrm\Agency;
use App\Models\Hrm\Attendance;
use App\Models\Hrm\BillingStatement;
use App\Models\Hrm\ManpowerRequest;
use App\Models\Hrm\Worker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Generate Statement — billing-period validation + billing safeguards.
 *
 * Today is pinned to 2026-10-18 (Asia/Manila) so every example in the task
 * specification can be asserted directly. The app migrations are MySQL-only,
 * so the minimal schema is rebuilt here like the other feature tests do.
 */
class AgencyBillingPeriodValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        // 2026-10-18, midday in the configured app timezone.
        $this->travelTo(Carbon::create(2026, 10, 18, 12, 0, 0, 'Asia/Manila'));
    }

    public function test_configured_timezone_is_asia_manila(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->assertSame('2026-10-18', today()->toDateString());
    }

    public function test_allows_billing_period_that_ends_in_the_past(): void
    {
        // October 1 → October 15 with an approved attendance inside.
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-05');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $statement = BillingStatement::firstOrFail();
        $this->assertSame(BillingStatement::STATUS_ISSUED, $statement->status);
        $this->assertSame('2026-10-01', $statement->period_start->toDateString());
        $this->assertSame('2026-10-15', $statement->period_end->toDateString());
        $this->assertGreaterThan(0, (float) $statement->labor_cost);
    }

    public function test_allows_period_that_starts_and_ends_today(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-18');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-18',
                'period_end' => '2026-10-18',
                'issue' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '2026-10-18',
            BillingStatement::firstOrFail()->period_end->toDateString()
        );
    }

    public function test_allows_an_unusually_old_billing_period(): void
    {
        // August 3 → October 1.
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-08-15');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-08-03',
                'period_end' => '2026-10-01',
                'issue' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('billing_statements', 1);
    }

    public function test_allows_saving_a_past_period_as_draft(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-05');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'issue' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(BillingStatement::STATUS_DRAFT, BillingStatement::firstOrFail()->status);
    }

    public function test_rejects_period_start_in_the_future(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-20');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-19',
                'period_end' => '2026-10-30',
                'issue' => true,
            ])
            ->assertSessionHasErrors([
                'period_start' => 'Period from cannot be later than today.',
            ]);

        $this->assertDatabaseCount('billing_statements', 0);
    }

    public function test_rejects_period_end_in_the_future(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-05');

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-19',
                'issue' => true,
            ])
            ->assertSessionHasErrors([
                'period_end' => 'Period to cannot be later than today.',
            ]);
    }

    public function test_rejects_period_end_before_period_start(): void
    {
        $agency = $this->agency();

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-16',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasErrors([
                'period_end' => 'Period to must be on or after Period from.',
            ]);

        $this->assertDatabaseCount('billing_statements', 0);
    }

    public function test_rejects_invalid_date_strings(): void
    {
        $agency = $this->agency();

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => 'not-a-date',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasErrors('period_start');
    }

    public function test_blocks_issuance_without_eligible_unbilled_attendance(): void
    {
        $agency = $this->agency();

        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasErrors([
                'period_start' => 'No eligible, unbilled attendance records were found for the selected agency and period.',
            ]);

        $this->assertDatabaseCount('billing_statements', 0);
    }

    public function test_excludes_attendance_already_billed_to_another_statement(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-05');

        $user = $this->hrmManager();

        // First statement bills October 1–15.
        $this->actingAs($user)
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNotNull(Attendance::firstOrFail()->billed_statement_id);

        // A second, non-overlapping period with no unbilled records is blocked.
        $this->actingAs($user)
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-16',
                'period_end' => '2026-10-18',
                'issue' => true,
            ])
            ->assertSessionHasErrors([
                'period_start' => 'No eligible, unbilled attendance records were found for the selected agency and period.',
            ]);

        $this->assertDatabaseCount('billing_statements', 1);
    }

    public function test_prevents_duplicate_billing_of_an_overlapping_period(): void
    {
        $agency = $this->agency();
        $this->attendanceFor($agency, '2026-10-05');
        $user = $this->hrmManager();

        $this->actingAs($user)
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-10-15',
                'issue' => true,
            ])
            ->assertSessionHasNoErrors();

        // Overlapping period is rejected before a second statement is created.
        $this->actingAs($user)
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-10',
                'period_end' => '2026-10-18',
                'issue' => true,
            ])
            ->assertSessionHasErrors('period_start');

        $this->assertDatabaseCount('billing_statements', 1);
    }

    public function test_direct_request_future_date_is_rejected_even_without_frontend(): void
    {
        $agency = $this->agency();

        // No frontend :max guard can help here — the server must reject.
        $this->actingAs($this->hrmManager())
            ->post(route('hrm.billing.store'), [
                'agency_id' => $agency->id,
                'period_start' => '2026-10-19',
                'period_end' => '2026-10-19',
                'issue' => true,
            ])
            ->assertSessionHasErrors('period_start');

        $this->assertDatabaseCount('billing_statements', 0);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function hrmManager(): User
    {
        $user = User::create([
            'name' => 'HR Manager',
            'email' => 'hr.manager@monti.test',
            'password' => Hash::make('secret123'),
        ]);
        User::where('id', $user->id)->update([
            'role' => 'HRM', 'position' => 'manager', 'is_active' => true,
        ]);

        return $user->fresh();
    }

    private function agency(): Agency
    {
        return Agency::create([
            'code' => 'AG-' . Str::upper(Str::random(4)),
            'name' => 'Test Agency',
            'service_fee_type' => 'percentage',
            'service_fee_value' => 10,
            'status' => Agency::STATUS_ACTIVE,
        ]);
    }

    private function attendanceFor(Agency $agency, string $date): Attendance
    {
        $worker = Worker::create([
            'code' => 'W-' . Str::upper(Str::random(4)),
            'agency_id' => $agency->id,
            'name' => 'Juan Dela Cruz',
            'email' => null,
            'daily_rate' => 500,
            'status' => Worker::STATUS_DEPLOYED,
        ]);

        $request = ManpowerRequest::create([
            'code' => 'REQ-' . Str::upper(Str::random(4)),
            'department' => 'Production',
            'position_skill' => 'Helper',
            'headcount' => 1,
            'agency_id' => $agency->id,
            'status' => ManpowerRequest::STATUS_OPEN,
        ]);

        return Attendance::create([
            'worker_id' => $worker->id,
            'request_id' => $request->id,
            'date' => $date,
            'time_in' => '08:00',
            'time_out' => '17:00',
            'hours' => 8,
            'overtime_hours' => 0,
            'status' => Attendance::STATUS_PRESENT,
            'approval' => Attendance::APPROVAL_APPROVED,
            'approved_at' => now(),
            'rate_snapshot' => 500,
        ]);
    }

    private function buildSchema(): void
    {
        foreach ([
            'users', 'page_permissions', 'workforce_permissions',
            'user_module_access', 'crm_page_permissions', 'crm_client_assignments',
            'hrm_departments', 'agencies', 'agency_activities',
            'billing_statements', 'workers', 'manpower_requests',
            'manpower_assignments', 'agency_attendances', 'attendance_logs',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('personal_email')->nullable();
            $t->string('password');
            $t->string('role')->default('HRM');
            $t->string('position')->default('staff');
            $t->string('employee_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('page_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('module');
            $t->string('page');
            $t->string('permission_level')->nullable();
            $t->timestamps();
        });

        Schema::create('workforce_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->timestamps();
        });

        Schema::create('user_module_access', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('module');
            $t->timestamps();
        });

        Schema::create('crm_page_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->string('page')->nullable();
            $t->timestamps();
        });

        Schema::create('crm_client_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('staff_id');
            $t->foreignId('client_id');
            $t->timestamps();
        });

        Schema::create('hrm_departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });

        Schema::create('agencies', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('name');
            $t->string('service_fee_type', 20)->default('percentage');
            $t->decimal('service_fee_value', 15, 2)->default(0);
            $t->decimal('ot_multiplier', 6, 2)->default(0);
            $t->date('contract_start')->nullable();
            $t->date('contract_end')->nullable();
            $t->string('status', 20)->default('Active');
            $t->timestamp('terminated_at')->nullable();
            $t->timestamps();
        });

        Schema::create('agency_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('agency_id');
            $t->foreignId('worker_id')->nullable();
            $t->foreignId('request_id')->nullable();
            $t->foreignId('statement_id')->nullable();
            $t->string('action', 60);
            $t->text('details')->nullable();
            $t->foreignId('actor_id')->nullable();
            $t->string('actor_name')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();
        });

        Schema::create('billing_statements', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('agency_id');
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status', 20)->default('Draft');
            $t->decimal('labor_cost', 15, 2)->default(0);
            $t->decimal('service_fee', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->date('paid_at')->nullable();
            $t->string('payment_reference')->nullable();
            $t->text('cancel_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('workers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->foreignId('agency_id');
            $t->string('name');
            $t->string('email')->nullable();
            $t->decimal('daily_rate', 12, 2)->nullable();
            $t->string('status', 20)->default('Available');
            $t->timestamps();
        });

        Schema::create('manpower_requests', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();
            $t->string('department');
            $t->string('position_skill');
            $t->unsignedInteger('headcount')->default(1);
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('shift')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('agency_id');
            $t->string('status', 20)->default('Draft');
            $t->text('cancel_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('manpower_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_id');
            $t->foreignId('worker_id');
            $t->foreignId('assigned_by')->nullable();
            $t->string('assigned_by_name')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('released_at')->nullable();
            $t->timestamps();
        });

        Schema::create('agency_attendances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('worker_id');
            $t->foreignId('request_id');
            $t->date('date');
            $t->string('time_in', 5)->nullable();
            $t->string('time_out', 5)->nullable();
            $t->decimal('hours', 6, 2)->nullable();
            $t->decimal('overtime_hours', 6, 2)->default(0);
            $t->string('status', 20)->default('Present');
            $t->string('approval', 20)->default('Draft');
            $t->text('review_reason')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('agency_confirmation', 20)->default('Pending');
            $t->text('dispute_reason')->nullable();
            $t->timestamp('disputed_at')->nullable();
            $t->string('disputed_by_name')->nullable();
            $t->decimal('rate_snapshot', 12, 2)->nullable();
            $t->text('remarks')->nullable();
            $t->unsignedBigInteger('billed_statement_id')->nullable();
            $t->timestamps();
            $t->unique(['worker_id', 'date']);
        });

        Schema::create('attendance_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id');
            $t->date('date');
            $t->string('clock_in')->nullable();
            $t->string('clock_out')->nullable();
            $t->string('status')->default('Absent');
            $t->timestamps();
        });
    }
}
