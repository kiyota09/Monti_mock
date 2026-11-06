<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tunable payroll constants. Single source of defaults; DB rows override.
 * Use PayrollSetting::cached() for key => typed value (falls back to
 * defaults when the table is missing/empty so calc never breaks).
 */
class PayrollSetting extends Model
{
    protected $fillable = ['key', 'group', 'label', 'value', 'value_type', 'description', 'updated_by'];

    public const CACHE_KEY = 'payroll_settings_map';

    /** [key => [group, label, value, value_type, description]] */
    public static function defaults(): array
    {
        return [
            // Earnings math
            'night_multiplier' => ['group' => 'earnings', 'label' => 'Night rate multiplier', 'value' => '1.10', 'value_type' => 'number', 'description' => 'Set the extra pay multiplier for eligible night hours. Example: 1.10 pays 110% of hourly pay for those hours. Used in new payroll calculations.'],
            'ot_multiplier' => ['group' => 'earnings', 'label' => 'Overtime multiplier', 'value' => '1.25', 'value_type' => 'number', 'description' => 'Set the hourly-pay multiplier for overtime hours. Example: 1.25 pays 125% of hourly pay. Used in new payroll calculations.'],
            'sun_multiplier' => ['group' => 'earnings', 'label' => 'Sun / special holiday multiplier', 'value' => '1.30', 'value_type' => 'number', 'description' => 'Set the multiplier used for eligible Sunday/rest-day or special-holiday hours. Example: 1.30 pays 130%. Used in new payroll calculations.'],
            'holiday_regular_worked' => ['group' => 'earnings', 'label' => 'Regular holiday worked rate', 'value' => '2.0', 'value_type' => 'number', 'description' => 'Set the daily-pay multiplier when an employee works on a regular holiday. Example: 2.0 means 200% of daily pay.'],
            'holiday_special_worked' => ['group' => 'earnings', 'label' => 'Special / non-working day worked rate', 'value' => '1.3', 'value_type' => 'number', 'description' => 'Set the daily-pay multiplier for work on a special or non-working holiday. Example: 1.3 means 130%.'],
            'holiday_regular_unworked' => ['group' => 'earnings', 'label' => 'Regular holiday unworked rate', 'value' => '1.0', 'value_type' => 'number', 'description' => 'Set the paid daily-pay multiplier for an eligible unworked regular holiday. Example: 1.0 means one full daily rate.'],
            'holiday_regular_rest_worked' => ['group' => 'earnings', 'label' => 'Regular + rest day worked rate', 'value' => '2.6', 'value_type' => 'number', 'description' => 'Set the daily-pay multiplier when a regular holiday falls on a rest day and is worked. This replaces the separate Sunday premium for that day.'],
            'holiday_special_rest_worked' => ['group' => 'earnings', 'label' => 'Special + rest day worked rate', 'value' => '1.5', 'value_type' => 'number', 'description' => 'Set the daily-pay multiplier when a special holiday falls on a rest day and is worked. This replaces the separate Sunday premium for that day.'],
            'late_minutes_divisor' => ['group' => 'earnings', 'label' => 'Late divisor (per minute)', 'value' => '60', 'value_type' => 'number', 'description' => 'Hourly pay is divided by this value to get the deduction per late minute. Increase it to lower the deduction rate; decrease it to raise the rate.'],
            'daily_divisor' => ['group' => 'earnings', 'label' => 'Daily divisor (DOLE)', 'value' => '26', 'value_type' => 'number', 'description' => 'Monthly salary is divided by this value to calculate daily pay when only a monthly salary is available. Set it to the divisor your company uses.'],
            'hours_per_day' => ['group' => 'earnings', 'label' => 'Hours per day', 'value' => '8', 'value_type' => 'number', 'description' => 'Daily pay is divided by this value to calculate hourly pay. Set standard paid hours per workday; this affects overtime and other hourly calculations.'],
            // Roster shift times
            'morning_start' => ['group' => 'shifts', 'label' => 'Morning start', 'value' => '08:00:00', 'value_type' => 'time', 'description' => 'Set the fallback morning shift start. Attendance uses it only when the employee roster has no readable shift start.'],
            'morning_end' => ['group' => 'shifts', 'label' => 'Morning end', 'value' => '17:00:00', 'value_type' => 'time', 'description' => 'Set the fallback morning shift end. Overtime detection uses it when the roster has no readable shift end.'],
            'afternoon_start' => ['group' => 'shifts', 'label' => 'Afternoon start', 'value' => '14:00:00', 'value_type' => 'time', 'description' => 'Set the fallback afternoon shift start. Attendance uses it only when the employee roster has no readable shift start.'],
            'afternoon_end' => ['group' => 'shifts', 'label' => 'Afternoon end', 'value' => '23:00:00', 'value_type' => 'time', 'description' => 'Set the fallback afternoon shift end. Overtime detection uses it when the roster has no readable shift end.'],
            'graveyard_start' => ['group' => 'shifts', 'label' => 'Graveyard start', 'value' => '22:00:00', 'value_type' => 'time', 'description' => 'Set the fallback graveyard shift start. Attendance uses it only when the employee roster has no readable shift start.'],
            'graveyard_end' => ['group' => 'shifts', 'label' => 'Graveyard end', 'value' => '06:00:00', 'value_type' => 'time', 'description' => 'Set the fallback graveyard shift end. An earlier end time is treated as the following day for overnight shifts.'],
            'night_window_start' => ['group' => 'shifts', 'label' => 'Night window start', 'value' => '22:00', 'value_type' => 'time', 'description' => 'Set when the night differential window begins. Hours inside this window can receive the night multiplier.'],
            'night_window_end' => ['group' => 'shifts', 'label' => 'Night window end', 'value' => '06:00', 'value_type' => 'time', 'description' => 'Set when the night differential window ends. An earlier end time is treated as the following day for an overnight window.'],
            // Pay calendar
            'pay_date_offset_days' => ['group' => 'calendar', 'label' => 'Pay date offset (days)', 'value' => '3', 'value_type' => 'number', 'description' => 'Set how many calendar days after the cutoff end the payday falls. For example, 3 schedules pay three days later.'],
            'pay_date_skip_sunday' => ['group' => 'calendar', 'label' => 'Skip Sunday pay date', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Turn on to move a calculated Sunday payday to Monday. Other payday weekdays are unchanged.'],
            'weekly_periods' => ['group' => 'calendar', 'label' => 'Weekly periods / year', 'value' => '52', 'value_type' => 'number', 'description' => 'Set the number of weekly periods used to scale per-period accruals. Keep this aligned with the weekly calendar.'],
            'weekly_anchor_date' => ['group' => 'calendar', 'label' => 'Weekly calendar start date', 'value' => '2000-01-03', 'value_type' => 'date', 'description' => 'Choose the first day of a weekly cutoff cycle. All weekly periods align to this date; changing it changes dates in the payroll period selector.'],
            'weekly_period_days' => ['group' => 'calendar', 'label' => 'Weekly period length (days)', 'value' => '7', 'value_type' => 'number', 'description' => 'Set inclusive calendar days per repeating period. For October 9–14, set the anchor to October 9 and this to 6; the next period starts October 15.'],
            'semimonthly_periods' => ['group' => 'calendar', 'label' => 'Semi-monthly periods / year', 'value' => '24', 'value_type' => 'number', 'description' => 'Set the semi-monthly period count used to scale accruals from monthly amounts. Usually 24, or two periods per month.'],
            'monthly_periods' => ['group' => 'calendar', 'label' => 'Monthly periods / year', 'value' => '12', 'value_type' => 'number', 'description' => 'Set the monthly period count used to scale accruals from annual amounts. Usually 12.'],
            // Attendance + payroll controls
            'history_max_days' => ['group' => 'attendance', 'label' => 'History range limit (days)', 'value' => '62', 'value_type' => 'number', 'description' => 'Set the largest date range allowed in attendance history. Larger ranges show more records and may take longer to load.'],
            'daily_per_page' => ['group' => 'attendance', 'label' => 'Daily rows per page', 'value' => '15', 'value_type' => 'number', 'description' => 'Set the number of attendance rows shown per daily-view page. This changes pagination only, not calculations.'],
            'history_per_page' => ['group' => 'attendance', 'label' => 'History rows per page', 'value' => '50', 'value_type' => 'number', 'description' => 'Set the number of records shown per attendance-history page. This changes pagination only, not calculations.'],
            'accrual_months' => ['group' => 'payroll', 'label' => '13th-month divisor', 'value' => '12', 'value_type' => 'number', 'description' => 'Set the divisor used to calculate 13th-month accrual from monthly-equivalent pay. A larger divisor lowers each accrual.'],
            'ot_warning_hours' => ['group' => 'payroll', 'label' => 'Overtime warning (hours)', 'value' => '40', 'value_type' => 'number', 'description' => 'Set the overtime threshold that triggers a payslip warning. This displays a warning only; it does not change overtime pay.'],
            'payment_ref_prefix' => ['group' => 'payroll', 'label' => 'Payment ref prefix', 'value' => 'PAY-', 'value_type' => 'text', 'description' => 'Set the text prepended to automatically generated payment references when payroll is marked paid.'],
            'default_payment_method' => ['group' => 'payroll', 'label' => 'Default payment method', 'value' => 'bank', 'value_type' => 'text', 'description' => 'Set the method preselected in the Mark paid form. The user can still choose a different method for an individual payment.'],
        ];
    }

    /** Typed key => value map, DB over defaults. Never throws. */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 3600, function () {
                $map = [];
                foreach (self::defaults() as $key => $def) {
                    $map[$key] = self::castValue($def['value'], $def['value_type']);
                }
                if (Schema::hasTable('payroll_settings')) {
                    foreach (self::query()->get(['key', 'value', 'value_type']) as $row) {
                        if (array_key_exists($row->key, $map)) {
                            $map[$row->key] = self::castValue($row->value, $row->value_type);
                        }
                    }
                }
                return $map;
            });
        } catch (\Throwable) {
            $map = [];
            foreach (self::defaults() as $key => $def) {
                $map[$key] = self::castValue($def['value'], $def['value_type']);
            }
            return $map;
        }
    }

    public static function castValue($value, string $type)
    {
        return match ($type) {
            'number' => (float) $value,
            'boolean' => in_array($value, [true, 1, '1', 'true', 'on'], true) ? 1 : 0,
            default => (string) ($value ?? ''),
        };
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
        }
    }
}
