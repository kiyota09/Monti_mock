<?php

namespace App\Services\Payroll;

use App\Models\Hrm\AttendanceLog;
use App\Models\Hrm\EmployeeShift;
use App\Models\Hrm\Holiday;
use App\Models\Hrm\HrmShift;
use App\Models\Hrm\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * THE authoritative payroll computation service.
 *
 * Both payroll flows — batch "Generate Payroll" and single "Add Employee" —
 * call PayrollSummaryService::summarize(), so holiday pay can never drift
 * between the two. There is no second copy of this logic anywhere.
 *
 * Rest-day definition: a date is a rest day when its weekday is OUTSIDE the
 * employee's HRM shift working_days (hrm_shift_user → hrm_shifts). With no
 * usable shift configuration, Sunday is the statutory default rest day —
 * this is the ONLY weekday-based assumption in the service. A Sunday that
 * falls on a scheduled workday gets no rest premium.
 *
 * Missing schedule: an assigned workday (not a rest day) with NO
 * EmployeeShift row is NOT silently treated as a rest day. It is skipped
 * for pay/absence and recorded in `unscheduled_dates` for HR review.
 *
 * Philippine holiday rules (Holiday Management is the single calendar):
 * - Regular, worked                        → 200% of daily
 * - Regular + rest day (Sun), worked        → 260% of daily
 * - Regular, unworked + eligible            → 100% paid, never absent
 * - Regular, unworked + ineligible         → unpaid, never absent
 * - Special / Non-Working Day, worked      → 130% of daily
 * - Special / Non-Working Day + rest, worked→ 150% of daily
 * - Special / Non-Working Day, unworked     → no pay, never absent
 * - Special Working Day                     → ordinary workday (normal rules)
 * - Required-to-Work + no show              → absent (after shift/leave checks)
 *
 * Eligibility (DOLE): unworked Regular is paid only when the employee was
 * present (clock-in) or on approved leave on the nearest preceding
 * scheduled workday (up to 7 days back). No history found → eligible.
 *
 * No double-counting: the holiday daily extra covers the first 8h only —
 * OT stays hourly on top; on holiday rest days the stacked daily rate
 * replaces the rest-day hourly premium (never both). Night differential is
 * time-based and independent of holiday status.
 *
 * Legacy `holidays.premium_rate` is informational only: nothing in this
 * service reads it, so it cannot conflict with these rates.
 */
class PayrollSummaryService
{
    // ------------------------------------------------------------------
    // Pure rule engine (no DB — unit-tested directly).
    // ------------------------------------------------------------------

    /**
     * Normalize a Holiday Management row (Holiday::rangeMap shape or null).
     */
    public static function resolveHolidayRule(?array $holiday): array
    {
        if (! $holiday) {
            return ['is_holiday' => false, 'type' => null, 'policy' => null, 'required' => false, 'non_working' => false, 'ordinary' => false];
        }
        $type = $holiday['type'] ?? null;
        $policy = $holiday['policy'] ?? 'non_working';
        $required = $policy === 'required_to_work';
        $ordinary = $type === 'special_working';

        return [
            'is_holiday' => true,
            'type' => $type,
            'policy' => $policy,
            'required' => $required,
            // A day is non-working when policy says so (legacy special_working
            // rows always count as working days).
            'non_working' => ! $required && ! $ordinary,
            'ordinary' => $ordinary,
        ];
    }

    /**
     * Outcome for a scheduled day with NO clock-in and no approved leave.
     * Returns ['pay' => extra multiple of dailyRate (0 = unpaid),
     *          'absent' => bool, 'count_holiday' => bool].
     * Rest days never produce absences (unless the holiday policy explicitly
     * required work that day).
     */
    public static function unworkedOutcome(array $rule, bool $eligible, array $rates, bool $isRestDay = false): array
    {
        if (! $rule['is_holiday'] || $rule['ordinary']) {
            if ($isRestDay) {
                return ['pay' => 0.0, 'absent' => false, 'count_holiday' => false];
            }

            return ['pay' => 0.0, 'absent' => true, 'count_holiday' => false];
        }
        if ($rule['type'] === 'regular' && ! $rule['required'] && $eligible) {
            return ['pay' => (float) $rates['regularUnworked'], 'absent' => false, 'count_holiday' => true];
        }
        if ($rule['type'] === 'regular' && ! $rule['required'] && ! $eligible) {
            return ['pay' => 0.0, 'absent' => false, 'count_holiday' => false];
        }
        if ($rule['non_working']) {
            // Special / Non-Working Day, not worked: no pay, never absent.
            return ['pay' => 0.0, 'absent' => false, 'count_holiday' => false];
        }
        // Required-to-work (any type) with no show: absence after all checks.
        return ['pay' => 0.0, 'absent' => true, 'count_holiday' => false];
    }

    /**
     * Extra multiple of the daily rate for a WORKED holiday day.
     * $isRestDay comes from the employee's rest-day configuration — never
     * from the weekday alone. Returns ['extra' => float,
     * 'count_holiday' => bool, 'skip_rest_premium' => bool].
     * `non_working` is priced identically to `special_non_working`
     * (130% / 150% on rest); `special_working` is ordinary (no extra).
     */
    public static function workedExtra(array $rule, bool $isRestDay, array $rates): array
    {
        if (! $rule['is_holiday'] || $rule['ordinary']) {
            return ['extra' => 0.0, 'count_holiday' => false, 'skip_rest_premium' => false];
        }
        if ($rule['type'] === 'regular') {
            if ($isRestDay) {
                return ['extra' => (float) $rates['regularRestWorked'] - 1, 'count_holiday' => true, 'skip_rest_premium' => true];
            }

            return ['extra' => (float) $rates['regularWorked'] - 1, 'count_holiday' => true, 'skip_rest_premium' => false];
        }
        if (in_array($rule['type'], ['special_non_working', 'non_working'], true)) {
            if ($isRestDay) {
                return ['extra' => (float) $rates['specialRestWorked'] - 1, 'count_holiday' => true, 'skip_rest_premium' => true];
            }

            return ['extra' => (float) $rates['specialWorked'] - 1, 'count_holiday' => true, 'skip_rest_premium' => false];
        }

        return ['extra' => 0.0, 'count_holiday' => false, 'skip_rest_premium' => false];
    }

    /**
     * Numeric setting with a floor: missing, non-numeric, or below-minimum
     * values fall back to the default instead of corrupting payroll math.
     */
    private static function settingNum(array $ps, string $key, float $default, float $min): float
    {
        $v = $ps[$key] ?? $default;
        if (! is_numeric($v) || (float) $v < $min) {
            return $default;
        }

        return (float) $v;
    }

    public static function defaultRates(array $ps): array
    {
        return [
            'dailyDivisor' => self::settingNum($ps, 'daily_divisor', 26.0, 0.000001),
            'hoursPerDay' => self::settingNum($ps, 'hours_per_day', 8.0, 0.000001),
            'lateDivisor' => self::settingNum($ps, 'late_minutes_divisor', 60.0, 0.000001),
            'otMult' => self::settingNum($ps, 'ot_multiplier', 1.25, 0.0),
            'nightMult' => self::settingNum($ps, 'night_multiplier', 1.10, 0.0),
            'sunMult' => self::settingNum($ps, 'sun_multiplier', 1.30, 0.0),
            'regularWorked' => self::settingNum($ps, 'holiday_regular_worked', 2.0, 1.0),
            'specialWorked' => self::settingNum($ps, 'holiday_special_worked', 1.3, 1.0),
            'regularUnworked' => self::settingNum($ps, 'holiday_regular_unworked', 1.0, 0.0),
            'regularRestWorked' => self::settingNum($ps, 'holiday_regular_rest_worked', 2.6, 1.0),
            'specialRestWorked' => self::settingNum($ps, 'holiday_special_rest_worked', 1.5, 1.0),
        ];
    }

    /**
     * Employee rest weekdays (0 = Sunday … 6 = Saturday) from their HRM
     * shift working_days. Unknown/invalid configuration → [0] (Sunday is
     * the statutory default rest day).
     */
    public static function restWeekdays(int $userId): array
    {
        $order = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];
        $working = [];
        try {
            $ids = DB::table('hrm_shift_user')->where('user_id', $userId)->pluck('shift_id')->all();
            if ($ids) {
                foreach (HrmShift::whereIn('id', $ids)->get(['working_days']) as $row) {
                    foreach ((array) ($row->working_days ?? []) as $day) {
                        if (isset($order[$day])) {
                            $working[$order[$day]] = true;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            return [0];
        }
        if (! $working) {
            return [0];
        }

        return array_values(array_diff([0, 1, 2, 3, 4, 5, 6], array_keys($working)));
    }

    // ------------------------------------------------------------------
    // Orchestration (Eloquent). Return shape is the legacy contract —
    // approval, payslip, and audit code read these exact keys.
    // ------------------------------------------------------------------

    public static function summarize($employee, $period, $payrollSet, ?array $settings = null): array
    {
        $ps = $settings ?? \App\Models\Hrm\PayrollSetting::cached();
        $rates = self::defaultRates($ps);
        $dailyDiv = $rates['dailyDivisor'];
        $hrsDay = $rates['hoursPerDay'];
        $lateDiv = $rates['lateDivisor'];
        $otMult = $rates['otMult'];
        $nightMult = $rates['nightMult'];
        $sunMult = $rates['sunMult'];
        $dailyRate = $payrollSet->base_salary / $dailyDiv; // DOLE standard divisor
        $daysWorked = 0;
        $scheduledShifts = 0;
        $absentDays = 0;
        $leaveDays = 0;
        $holidayDaysWorked = 0;
        $totalDaysAmt = 0;
        $lateMinutesTotal = 0;
        $lateDeductionTotal = 0;
        $overtimeHours = 0;
        $otAmt = 0;
        $nightHours = 0;
        $nightAmt = 0;
        $sundayHours = 0;
        $sunAmt = 0;
        $holidayAmt = 0;
        $presentDates = [];
        $absentDates = [];
        $leaveDates = [];
        $unscheduledDates = [];

        $dates = $period->toArray();
        // Single authoritative calendar read for the whole cutoff.
        $holAll = [];
        if ($dates) {
            $holAll = Holiday::rangeMap($dates[0]->toDateString(), end($dates)->toDateString());
        }
        // Employee rest-day configuration (HRM shift working_days).
        $restWeekdays = self::restWeekdays((int) $employee->id);
        foreach ($dates as $date) {
            $dateStr = $date->toDateString();
            $isRestDay = in_array((int) $date->dayOfWeek, $restWeekdays, true);

            // Assigned shift first. A missing row on an assigned workday is
            // NOT a rest day — flag it for HR review instead of assuming.
            $shift = EmployeeShift::where('user_id', $employee->id)
                ->where('effective_date', $dateStr)
                ->first();
            if (! $shift) {
                if ($isRestDay) continue; // assigned rest day: silent
                $unscheduledDates[] = $dateStr; // missing schedule: HR review, never absent
                continue;
            }

            $scheduledShifts++;

            // Approved leave = excused, not absent.
            $onLeave = LeaveRequest::where('user_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $dateStr)
                ->whereDate('end_date', '>=', $dateStr)
                ->exists();
            if ($onLeave) {
                $leaveDays++;
                $leaveDates[] = $dateStr;
                continue;
            }

            // Holiday rule for this cutoff date (type + working policy).
            $rule = self::resolveHolidayRule($holAll[$dateStr] ?? null);

            // Attendance log.
            $log = AttendanceLog::where('user_id', $employee->id)
                ->where('date', $dateStr)
                ->first();

            if (! $log || ! $log->clock_in) {
                $outcome = self::unworkedOutcome($rule, self::isEligibleForUnworkedRegular((int) $employee->id, $dateStr), $rates, $isRestDay);
                if ($outcome['pay'] > 0) {
                    $holidayAmt += $dailyRate * $outcome['pay'];
                }
                if ($outcome['count_holiday']) {
                    $holidayDaysWorked++;
                }
                if ($outcome['absent']) {
                    $absentDays++; // scheduled but no show (ordinary day or required holiday)
                    $absentDates[] = $dateStr;
                }
                continue;
            }

            $daysWorked++;
            $presentDates[] = $dateStr;
            $totalDaysAmt += $dailyRate;

            // Shift times (roster schedule_range first, e.g. "2PM - 11PM").
            $shiftStart = Carbon::parse($dateStr.' '.self::getShiftStart($shift), 'Asia/Manila');
            $shiftEnd = Carbon::parse($dateStr.' '.self::getShiftEnd($shift), 'Asia/Manila');
            if ($shiftEnd->lt($shiftStart)) {
                $shiftEnd->addDay();
            }

            $clockIn = Carbon::parse($dateStr.' '.$log->clock_in, 'Asia/Manila');
            $clockOut = $log->clock_out ? Carbon::parse($dateStr.' '.$log->clock_out, 'Asia/Manila') : $shiftEnd;

            // Late deduction: hourly ÷ divisor (Payroll Settings).
            $hourlyRate = $dailyRate > 0 ? $dailyRate / $hrsDay : 0;
            if ($clockIn->gt($shiftStart)) {
                $lateMins = (int) abs($clockIn->diffInMinutes($shiftStart));
                $lateMinutesTotal += $lateMins;
                $lateDeductionTotal += $lateMins * ($hourlyRate / $lateDiv);
            }

            // Overtime: hourly × OT multiplier (independent of holiday/OT overlap —
            // the holiday extra below is daily-based for the first 8h only).
            if ($clockOut->gt($shiftEnd)) {
                $otMins = (int) abs($clockOut->diffInMinutes($shiftEnd));
                $otHours = $otMins / 60;
                $overtimeHours += $otHours;
                $otAmt += $otHours * $hourlyRate * $otMult;
            }

            // Night differential: hourly × night multiplier (time-based,
            // independent of holiday status).
            $nightMins = self::calculateNightMinutes($clockIn, $clockOut);
            $nightHrs = $nightMins / 60;
            $nightHours += $nightHrs;
            $nightAmt += $nightHrs * $hourlyRate * $nightMult;

            // Holiday daily premium (replaces — never stacks with — the
            // rest-day hourly premium on holiday rest days).
            $worked = self::workedExtra($rule, $isRestDay, $rates);
            if ($worked['extra'] > 0) {
                $holidayAmt += $dailyRate * $worked['extra'];
                $holidayDaysWorked++;
            }

            // Rest-day work: hourly × rest multiplier. Skipped when a stacked
            // holiday daily rate already covers the day; never applied merely
            // because the calendar day is Sunday — $isRestDay comes from the
            // employee's rest-day configuration. A special holiday on an
            // ordinary workday must NOT earn a rest premium on top of its
            // 130% daily rate (DOLE: 130% total, 150% only on a rest day).
            if ($isRestDay && ! $worked['skip_rest_premium']) {
                $hoursWorked = abs($clockIn->diffInHours($clockOut));
                $sundayHours += $hoursWorked;
                $sunAmt += $hoursWorked * $hourlyRate * $sunMult;
            }
        }

        return [
            'days_worked' => $daysWorked,
            'scheduled_shifts' => $scheduledShifts,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'present_dates' => $presentDates,
            'absent_dates' => $absentDates,
            'leave_dates' => $leaveDates,
            'unscheduled_dates' => $unscheduledDates,
            'holiday_days' => $holidayDaysWorked,
            'daily_rate' => $dailyRate,
            'total_days_amt' => $totalDaysAmt,
            'late_minutes' => $lateMinutesTotal,
            'late_rate_min' => $dailyRate > 0 ? ($dailyRate / $hrsDay) / $lateDiv : 0,
            'late_total_deduction' => $lateDeductionTotal,
            'overtime_hours' => $overtimeHours,
            'ot_rate' => $dailyRate > 0 ? ($dailyRate / $hrsDay) * $otMult : 0,
            'ot_amt' => $otAmt,
            'night_hours' => $nightHours,
            'night_rate' => $dailyRate > 0 ? ($dailyRate / $hrsDay) * $nightMult : 0,
            'night_amt' => $nightAmt,
            'sunday_restday_hours' => $sundayHours,
            'sun_sp_rate' => $dailyRate > 0 ? ($dailyRate / $hrsDay) * $sunMult : 0,
            'sun_sp_amt' => $sunAmt,
            'holiday_amt' => $holidayAmt,
        ];
    }

    /**
     * DOLE eligibility for an unworked Regular holiday: present (clock-in)
     * or on approved leave on the nearest preceding scheduled workday
     * (up to 7 days back; non-working holidays don't count). No history
     * found (e.g. new hire) → eligible.
     */
    public static function isEligibleForUnworkedRegular(int $userId, string $dateStr): bool
    {
        $end = Carbon::parse(substr($dateStr, 0, 10))->subDay()->toDateString();
        $start = Carbon::parse(substr($dateStr, 0, 10))->subDays(7)->toDateString();

        $shifts = EmployeeShift::where('user_id', $userId)
            ->whereBetween('effective_date', [$start, $end])
            ->pluck('effective_date')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->flip()
            ->all();
        if (! $shifts) {
            return true;
        }
        $hols = Holiday::nonWorkingMap($start, $end);
        // DOLE: when the day before the holiday is a rest day or a
        // non-working day, entitlement looks at the last preceding workday.
        // Rest days therefore never establish (or break) eligibility.
        $rest = self::restWeekdays($userId);

        $cursor = Carbon::parse($end);
        $floor = Carbon::parse($start);
        while ($cursor->greaterThanOrEqualTo($floor)) {
            $key = $cursor->toDateString();
            if (in_array((int) $cursor->dayOfWeek, $rest, true)) {
                $cursor->subDay();
                continue;
            }
            if (isset($shifts[$key]) && ! isset($hols[$key])) {
                $log = AttendanceLog::where('user_id', $userId)->where('date', $key)->first();
                if ($log && $log->clock_in) {
                    return true;
                }
                $onLeave = LeaveRequest::where('user_id', $userId)
                    ->where('status', 'approved')
                    ->whereDate('start_date', '<=', $key)
                    ->whereDate('end_date', '>=', $key)
                    ->exists();
                if ($onLeave) {
                    return true;
                }

                return false;
            }
            $cursor->subDay();
        }

        return true;
    }

    /** Roster-aware shift bounds. Parses schedule_range first (e.g. "2PM - 11PM"), else roster defaults. */
    public static function parseRangeTime(?string $range, int $part): ?string
    {
        if (! $range) return null;
        $segments = explode('-', $range);
        $token = trim($segments[$part] ?? '');
        if ($token === '' || ! preg_match('/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/i', $token, $m)) return null;
        $h = (int) $m[1]; $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0; $ap = strtoupper($m[3]);
        if ($ap === 'PM' && $h < 12) $h += 12;
        if ($ap === 'AM' && $h == 12) $h = 0;

        return sprintf('%02d:%02d:00', $h, $min);
    }

    /** Shift-bound defaults live in Payroll Settings; roster schedule_range still wins. */
    public static function shiftDefaults(): array
    {
        $ps = \App\Models\Hrm\PayrollSetting::cached();

        return [
            'Morning' => [(string) ($ps['morning_start'] ?? '08:00:00'), (string) ($ps['morning_end'] ?? '17:00:00')],
            'Afternoon' => [(string) ($ps['afternoon_start'] ?? '14:00:00'), (string) ($ps['afternoon_end'] ?? '23:00:00')],
            'Graveyard' => [(string) ($ps['graveyard_start'] ?? '22:00:00'), (string) ($ps['graveyard_end'] ?? '06:00:00')],
        ];
    }

    public static function getShiftStart($shift): string
    {
        if (is_object($shift)) {
            $parsed = self::parseRangeTime($shift->schedule_range ?? null, 0);
            if ($parsed) return $parsed;
            $shift = $shift->shift_type ?? null;
        }
        $d = self::shiftDefaults();

        return $d[$shift][0] ?? $d['Morning'][0];
    }

    public static function getShiftEnd($shift): string
    {
        if (is_object($shift)) {
            $parsed = self::parseRangeTime($shift->schedule_range ?? null, 1);
            if ($parsed) return $parsed;
            $shift = $shift->shift_type ?? null;
        }
        $d = self::shiftDefaults();

        return $d[$shift][1] ?? $d['Morning'][1];
    }

    public static function calculateNightMinutes($clockIn, $clockOut): int
    {
        $ps = \App\Models\Hrm\PayrollSetting::cached();
        [$wsH, $wsM] = array_map('intval', array_pad(explode(':', (string) ($ps['night_window_start'] ?? '22:00')), 2, 0));
        [$weH, $weM] = array_map('intval', array_pad(explode(':', (string) ($ps['night_window_end'] ?? '06:00')), 2, 0));
        $nightStart = $clockIn->copy()->setTime($wsH, $wsM);
        $nightEnd = $clockIn->copy()->addDay()->setTime($weH, $weM);
        $overlapStart = $clockIn->max($nightStart);
        $overlapEnd = $clockOut->min($nightEnd);

        return $overlapStart->lt($overlapEnd) ? (int) abs($overlapStart->diffInMinutes($overlapEnd)) : 0;
    }

    public static function isHoliday($date, $type): bool
    {
        return Holiday::where('holiday_date', $date)->where('holiday_type', $type)->exists();
    }
}
