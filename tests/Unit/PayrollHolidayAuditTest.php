<?php

namespace Tests\Unit;

use App\Services\Payroll\PayrollSummaryService;
use App\Support\PayrollCalc;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Audit regression tests for the Holiday/Attendance/Payroll integration.
 *
 * Pins the fixes from the final review:
 *  - special holidays on ordinary weekdays earn NO rest premium on top;
 *  - night_rate is hourly-based (consistent with ot/sun rates);
 *  - unworked-regular eligibility skips rest days (DOLE preceding-workday rule);
 *  - `non_working` prices like `special_non_working`;
 *  - invalid settings fall back to defaults instead of corrupting math.
 *
 * Base salary 26000 → daily 1000, hourly 125. Oct 2026 reference:
 * Oct 1 = Thu, Oct 3 = Sat, Oct 4 = Sun (default rest), Oct 5 = Mon.
 */
class PayrollHolidayAuditTest extends TestCase
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

    private function summarize(int $userId, string $from, string $to): array
    {
        return PayrollSummaryService::summarize(
            (object) ['id' => $userId],
            CarbonPeriod::create($from, $to),
            (object) ['base_salary' => 26000],
            $this->settings()
        );
    }

    private function shift(int $user, string $date): void
    {
        DB::table('employee_shifts')->insert([
            'user_id' => $user, 'effective_date' => $date,
            'shift_type' => 'Morning', 'schedule_range' => '8:00 AM - 5:00 PM',
        ]);
    }

    private function log(int $user, string $date, ?string $in, ?string $out = null): void
    {
        DB::table('attendance_logs')->insert([
            'user_id' => $user, 'date' => $date,
            'clock_in' => $in, 'clock_out' => $out, 'status' => 'On-Time',
        ]);
    }

    private function holiday(string $date, string $name, string $type, string $policy = 'non_working'): void
    {
        DB::table('holidays')->insert([
            'holiday_date' => $date, 'holiday_name' => $name,
            'holiday_type' => $type, 'work_policy' => $policy,
        ]);
    }

    public function test_special_worked_on_weekday_earns_no_rest_premium(): void
    {
        // Oct 1 2026 = Thursday (ordinary workday, not a rest day).
        $this->shift(1, '2026-10-01');
        $this->holiday('2026-10-01', 'Special', 'special_non_working');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        $this->assertEqualsWithDelta(300.0, $s['holiday_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['sun_sp_amt'], 0.01);
        $this->assertEqualsWithDelta(0.0, $s['sunday_restday_hours'], 0.01);
    }

    public function test_non_working_type_prices_like_special(): void
    {
        $rates = PayrollSummaryService::defaultRates($this->settings());

        $rule = PayrollSummaryService::resolveHolidayRule(['type' => 'non_working', 'policy' => 'non_working']);
        $w = PayrollSummaryService::workedExtra($rule, false, $rates);
        $this->assertEqualsWithDelta(0.3, $w['extra'], 0.0001);
        $wr = PayrollSummaryService::workedExtra($rule, true, $rates);
        $this->assertEqualsWithDelta(0.5, $wr['extra'], 0.0001);
        $this->assertTrue($wr['skip_rest_premium']);

        $u = PayrollSummaryService::unworkedOutcome($rule, true, $rates);
        $this->assertSame(0.0, $u['pay']);
        $this->assertFalse($u['absent']);
    }

    public function test_night_rate_is_hourly_based(): void
    {
        $this->shift(1, '2026-10-01');
        $this->log(1, '2026-10-01', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-01', '2026-10-01');

        // daily 1000 → hourly 125 → night rate 125 × 1.10 = 137.50.
        $this->assertEqualsWithDelta(137.50, $s['night_rate'], 0.01);

        $c = PayrollCalc::calc(['days' => 1, 'daily' => 1000], 'semi_monthly');
        $this->assertEqualsWithDelta(137.50, $c['night_rate'], 0.01);
    }

    public function test_eligibility_skips_rest_day_before_holiday(): void
    {
        // Sat Oct 3 worked, Sun Oct 4 has a shift row but no show (rest day),
        // Mon Oct 5 regular holiday unworked. Eligibility must look past the
        // Sunday rest day to Saturday → eligible → 100% paid.
        $this->shift(1, '2026-10-03');
        $this->shift(1, '2026-10-04');
        $this->shift(1, '2026-10-05');
        $this->holiday('2026-10-05', 'Monday Regular', 'regular');
        $this->log(1, '2026-10-03', '8:00 AM', '5:00 PM');

        $s = $this->summarize(1, '2026-10-03', '2026-10-05');

        $this->assertEqualsWithDelta(1000.0, $s['holiday_amt'], 0.01);
        $this->assertSame(1, $s['holiday_days']);
        // Only Saturday counts as absent-free present; Sunday rest is silent.
        $this->assertNotContains('2026-10-05', $s['absent_dates']);
    }

    public function test_invalid_settings_fall_back_to_defaults(): void
    {
        $rates = PayrollSummaryService::defaultRates([
            'daily_divisor' => 0, 'hours_per_day' => -5,
            'ot_multiplier' => 'nan', 'night_multiplier' => null,
            'holiday_regular_worked' => 0.2, // below min 1.0 → 2.0
            'holiday_regular_unworked' => 'abc', // → 1.0
        ]);

        $this->assertSame(26.0, $rates['dailyDivisor']);
        $this->assertSame(8.0, $rates['hoursPerDay']);
        $this->assertSame(1.25, $rates['otMult']);
        $this->assertSame(1.10, $rates['nightMult']);
        $this->assertSame(2.0, $rates['regularWorked']);
        $this->assertSame(1.0, $rates['regularUnworked']);
    }
}
