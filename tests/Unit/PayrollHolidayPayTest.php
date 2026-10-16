<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollSummaryService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Holiday-pay verification for the Monti ERP payroll service.
 *
 * One authoritative service (PayrollSummaryService::summarize) feeds BOTH
 * "Generate Payroll" and "Add Employee". These tests pin the computed
 * totals: regular / special non-working / special working days, unworked
 * holidays, rest days, overtime separation, approved leave, and cutoff
 * boundaries. Oct 2026 reference: Oct 1 = Thu, Oct 2 = Fri, Oct 3 = Sat,
 * Oct 4 = Sun, Oct 5 = Mon. Base salary 26000 → daily rate 1000.
 */
class PayrollHolidayPayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('effective_date');
            $table->string('shift_type')->nullable();
            $table->string('schedule_range')->nullable();
            $table->string('status')->nullable();
            $table->softDeletes();
        });
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('date');
            $table->string('clock_in')->nullable();
            $table->string('clock_out')->nullable();
            $table->string('status')->nullable();
            $table->string('remarks')->nullable();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('leave_type')->nullable();
            $table->string('start_date');
            $table->string('end_date');
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
        });
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('holiday_date')->unique();
            $table->string('holiday_name');
            $table->string('holiday_type')->default('regular');
            $table->string('work_policy')->default('non_working');
            $table->decimal('premium_rate', 5, 2)->default(1.00);
            $table->string('status')->default('approved');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('employee_shifts');

        parent::tearDown();
    }

    // ---- fixtures ----------------------------------------------------

    private function settings(): array
    {
        return [
            'daily_divisor' => 26, 'hours_per_day' => 8, 'late_minutes_divisor' => 60,
            'ot_multiplier' => 1.25, 'night_multiplier' => 1.10, 'sun_multiplier' => 1.30,
            'holiday_regular_worked' => 2.0, 'holiday_special_worked' => 1.3,
            'holiday_regular_unworked' => 1.0, 'holiday_regular_rest_worked' => 2.6,
            'holiday_special_rest_worked' => 1.5,
        ];
    }

    private function employee(int $id = 1): object
    {
        return (object) ['id' => $id];
    }

    private function paySet(): object
    {
        return (object) ['base_salary' => 26000];
    }

    private function summarize(int $userId, string $from, string $to): array
    {
        return PayrollSummaryService::summarize(
            $this->employee($userId),
            CarbonPeriod::create($from, $to),
            $this->paySet(),
            $this->settings()
        );
    }

    private function shift(int $user, string $date, string $type = 'Morning', string $range = '8:00 AM - 5:00 PM'): void
    {
        DB::table('employee_shifts')->insert([
            'user_id' => $user, 'effective_date' => $date, 'shift_type' => $type, 'schedule_range' => $range,
        ]);
    }

    private function log(int $user, string $date, ?string $in, ?string $out = null, string $status = 'On-Time'): void
    {
        DB::table('attendance_logs')->insert([
            'user_id' => $user, 'date' => $date, 'clock_in' => $in, 'clock_out' => $out, 'status' => $status,
        ]);
    }

    private function leave(int $user, string $from, string $to, string $status = 'approved'): void
    {
        DB::table('leave_requests')->insert([
            'user_id' => $user, 'leave_type' => 'vacation', 'start_date' => $from, 'end_date' => $to, 'status' => $status,
        ]);
    }

    private function holiday(string $date, string $name, string $type, string $policy = 'non_working'): void
    {
        DB::table('holidays')->insert([
            'holiday_date' => $date, 'holiday_name' => $name, 'holiday_type' => $type, 'work_policy' => $policy,
        ]);
    }

    // ---- worked holidays ----------------------------------------------

    public function test_regular_holiday_worked_pays_200_percent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Regular', 'regular');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $s['days_worked']);
        $this->assertEqualsWithDelta(1000.0, $s['total_days_amt'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $s['holiday_amt'], 0.01); // extra 100% on top of base
        $this->assertSame(1, $s['holiday_days']);
        $this->assertSame(0, $s['absent_days']);
    }

    public function test_special_non_working_worked_pays_130_percent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Special', 'special_non_working');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertEqualsWithDelta(300.0, $s['holiday_amt'], 0.01);
        $this->assertSame(1, $s['holiday_days']);
        $this->assertSame(0, $s['absent_days']);
    }

    public function test_special_working_day_is_an_ordinary_workday(): void
    {
        // Unworked special working day → absent, no holiday money.
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Special Working', 'special_working', 'required_to_work');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $s['absent_days']);
        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
        $this->assertSame(0, $s['holiday_days']);

        // Worked special working day → base pay only, no holiday extra.
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');
        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $s['days_worked']);
        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
    }

    // ---- unworked holidays ----------------------------------------------

    public function test_unworked_regular_holiday_paid_when_eligible(): void
    {
        $this->shift(1, '2026-09-30');
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Regular', 'regular');
        $this->log(1, '2026-09-30', '8:00 AM', '5:00 PM'); // worked day before → eligible

        $s = $this->summarize(1, '2026-09-30', '2026-10-01');

        $this->assertEqualsWithDelta(1000.0, $s['holiday_amt'], 0.01); // 100% without working
        $this->assertSame(1, $s['holiday_days']);
        $this->assertSame(0, $s['absent_days']);
        $this->assertSame(['2026-09-30'], $s['present_dates']);
    }

    public function test_unworked_regular_holiday_unpaid_but_not_absent_when_ineligible(): void
    {
        $this->shift(1, '2026-09-30');
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Regular', 'regular');
        // No log on Sep 30 → absent there → ineligible for Oct 1 holiday pay.

        $s = $this->summarize(1, '2026-09-30', '2026-10-01');

        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
        $this->assertSame(['2026-09-30'], $s['absent_dates']); // holiday itself is NOT an absence
        $this->assertSame(1, $s['absent_days']);
    }

    public function test_unworked_special_non_working_is_skipped_not_absent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Special', 'special_non_working');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(0, $s['absent_days']);
        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
        $this->assertSame(0, $s['days_worked']);
    }

    public function test_required_to_work_holiday_no_show_is_absent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Regular', 'regular', 'required_to_work');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $s['absent_days']);
        $this->assertSame(['2026-10-01'], $s['absent_dates']);
        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
    }

    // ---- rest days, leave, overtime ---------------------------------------

    public function test_rest_day_without_shift_is_never_absent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');
        // Oct 2–3 have no shift → rest days.

        $s = $this->summarize(1, '2026-10-01', '2026-10-03');

        $this->assertSame(1, $s['scheduled_shifts']);
        $this->assertSame(0, $s['absent_days']);
    }

    public function test_approved_leave_is_excused_not_absent(): void
    {
        $this->shift(1, '2026-10-01');
        $this->leave(1, '2026-10-01', '2026-10-01');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertSame(1, $s['leave_days']);
        $this->assertSame(0, $s['absent_days']);
    }

    public function test_overtime_on_regular_holiday_is_separate_no_double_count(): void
    {
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Test Regular', 'regular');
        $this->log(1, '2026-10-01', '8:00 AM', '8:00 PM'); // 3h OT past 5PM

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertEqualsWithDelta(3.0, $s['overtime_hours'], 0.01);
        $this->assertEqualsWithDelta(3 * 125 * 1.25, $s['ot_amt'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $s['holiday_amt'], 0.01); // exactly the daily extra
    }

    // ---- rest-day stacking --------------------------------------------------

    public function test_regular_holiday_on_sunday_pays_260_without_sunday_stack(): void
    {
        $this->shift(1, '2026-10-04'); // Sunday
        $this->holiday('2026-10-04', 'Sunday Regular', 'regular');
        $this->log(1, '2026-10-04', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-04', '2026-10-04');

        $this->assertEqualsWithDelta(1600.0, $s['holiday_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['sun_sp_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['sunday_restday_hours'], 0.01);
    }

    public function test_special_holiday_on_sunday_pays_150_without_stack(): void
    {
        $this->shift(1, '2026-10-04'); // Sunday
        $this->holiday('2026-10-04', 'Sunday Special', 'special_non_working');
        $this->log(1, '2026-10-04', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-04', '2026-10-04');

        $this->assertEqualsWithDelta(500.0, $s['holiday_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['sun_sp_amt'], 0.01);
    }

    public function test_plain_sunday_worked_keeps_sunday_premium(): void
    {
        $this->shift(1, '2026-10-04'); // Sunday, no holiday
        $this->log(1, '2026-10-04', '8:00 AM', '5:00 PM'); // 9h

        $s = $this->summarize(1, '2026-10-04', '2026-10-04');

        $this->assertEqualsWithDelta(9 * 125 * 1.3, $s['sun_sp_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['holiday_amt'], 0.01);
    }

    // ---- cutoff boundaries ----------------------------------------------------

    public function test_cutoff_boundaries_only_count_holidays_inside(): void
    {
        $this->shift(1, '2026-10-01');
        $this->shift(1, '2026-10-02');
        $this->holiday('2026-09-30', 'Before Cutoff', 'regular'); // outside → ignored
        $this->holiday('2026-10-02', 'Cutoff End Regular', 'regular');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM'); // eligible for Oct 2

        $s = $this->summarize(1, '2026-10-01', '2026-10-02');

        $this->assertEqualsWithDelta(1000.0, $s['holiday_amt'], 0.01);
        $this->assertSame(['2026-10-01'], $s['present_dates']);
        $this->assertSame([], $s['absent_dates']);
    }

    // ---- pure rule engine -------------------------------------------------------

    public function test_rule_normalization(): void
    {
        $none = PayrollSummaryService::resolveHolidayRule(null);
        $this->assertFalse($none['is_holiday']);

        $special = PayrollSummaryService::resolveHolidayRule(['type' => 'special_working', 'policy' => 'required_to_work']);
        $this->assertTrue($special['ordinary']);
        $this->assertFalse($special['non_working']);

        $req = PayrollSummaryService::resolveHolidayRule(['type' => 'regular', 'policy' => 'required_to_work']);
        $this->assertTrue($req['required']);
        $this->assertFalse($req['non_working']);

        $plain = PayrollSummaryService::resolveHolidayRule(['type' => 'regular', 'policy' => 'non_working']);
        $this->assertTrue($plain['non_working']);
    }

    public function test_pure_outcomes_match_ph_table(): void
    {
        $rates = PayrollSummaryService::defaultRates($this->settings());

        $reg = PayrollSummaryService::resolveHolidayRule(['type' => 'regular', 'policy' => 'non_working']);
        $this->assertSame(
            ['pay' => 1.0, 'absent' => false, 'count_holiday' => true],
            PayrollSummaryService::unworkedOutcome($reg, true, $rates)
        );
        $this->assertSame(
            ['pay' => 0.0, 'absent' => false, 'count_holiday' => false],
            PayrollSummaryService::unworkedOutcome($reg, false, $rates)
        );
        $w = PayrollSummaryService::workedExtra($reg, false, $rates);
        $this->assertEqualsWithDelta(1.0, $w['extra'], 0.0001);
        $this->assertTrue($w['count_holiday']);
        $this->assertFalse($w['skip_rest_premium']);
        $w = PayrollSummaryService::workedExtra($reg, true, $rates);
        $this->assertEqualsWithDelta(1.6, $w['extra'], 0.0001);
        $this->assertTrue($w['count_holiday']);
        $this->assertTrue($w['skip_rest_premium']);

        $sp = PayrollSummaryService::resolveHolidayRule(['type' => 'special_non_working', 'policy' => 'non_working']);
        $this->assertSame(
            ['pay' => 0.0, 'absent' => false, 'count_holiday' => false],
            PayrollSummaryService::unworkedOutcome($sp, true, $rates)
        );
        $w = PayrollSummaryService::workedExtra($sp, false, $rates);
        $this->assertEqualsWithDelta(0.3, $w['extra'], 0.0001);
        $this->assertTrue($w['count_holiday']);
        $this->assertFalse($w['skip_rest_premium']);
        $w = PayrollSummaryService::workedExtra($sp, true, $rates);
        $this->assertEqualsWithDelta(0.5, $w['extra'], 0.0001);
        $this->assertTrue($w['count_holiday']);
        $this->assertTrue($w['skip_rest_premium']);
    }
}
