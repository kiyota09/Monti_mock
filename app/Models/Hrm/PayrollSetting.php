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
            'night_multiplier' => ['group' => 'earnings', 'label' => 'Night rate multiplier', 'value' => '1.10', 'value_type' => 'number', 'description' => 'Night rate = daily × this. Amount = night hours × hourly × this.'],
            'ot_multiplier' => ['group' => 'earnings', 'label' => 'Overtime multiplier', 'value' => '1.25', 'value_type' => 'number', 'description' => 'OT rate = hourly × this (125% of hourly rate).'],
            'sun_multiplier' => ['group' => 'earnings', 'label' => 'Sun / special holiday multiplier', 'value' => '1.30', 'value_type' => 'number', 'description' => 'Sun/SP rate = hourly × this (130% of hourly rate).'],
            'holiday_regular_worked' => ['group' => 'earnings', 'label' => 'Regular holiday worked rate', 'value' => '2.0', 'value_type' => 'number', 'description' => 'PH rate when working a Regular holiday (200% of daily). Premium added on top of the base day.'],
            'holiday_special_worked' => ['group' => 'earnings', 'label' => 'Special / non-working day worked rate', 'value' => '1.3', 'value_type' => 'number', 'description' => 'PH rate when working a Special or Non-Working Day (130% of daily).'],
            'holiday_regular_unworked' => ['group' => 'earnings', 'label' => 'Regular holiday unworked rate', 'value' => '1.0', 'value_type' => 'number', 'description' => 'PH rate for an unworked Regular holiday (100% of daily, paid).'],
            'holiday_regular_rest_worked' => ['group' => 'earnings', 'label' => 'Regular + rest day worked rate', 'value' => '2.6', 'value_type' => 'number', 'description' => 'PH rate when a Regular holiday is worked on a rest day (260% of daily, replaces the Sunday premium).'],
            'holiday_special_rest_worked' => ['group' => 'earnings', 'label' => 'Special + rest day worked rate', 'value' => '1.5', 'value_type' => 'number', 'description' => 'PH rate when a Special/Non-Working Day is worked on a rest day (150% of daily, replaces the Sunday premium).'],
            'late_minutes_divisor' => ['group' => 'earnings', 'label' => 'Late divisor (per minute)', 'value' => '60', 'value_type' => 'number', 'description' => 'Late rate/min = hourly ÷ this.'],
            'daily_divisor' => ['group' => 'earnings', 'label' => 'Daily divisor (DOLE)', 'value' => '26', 'value_type' => 'number', 'description' => 'Daily rate = monthly ÷ this when only monthly salary is known.'],
            'hours_per_day' => ['group' => 'earnings', 'label' => 'Hours per day', 'value' => '8', 'value_type' => 'number', 'description' => 'Hourly rate = daily ÷ this.'],
            // Roster shift times
            'morning_start' => ['group' => 'shifts', 'label' => 'Morning start', 'value' => '08:00:00', 'value_type' => 'time', 'description' => 'Fallback when roster schedule_range has no parseable start.'],
            'morning_end' => ['group' => 'shifts', 'label' => 'Morning end', 'value' => '17:00:00', 'value_type' => 'time', 'description' => 'Overtime starts after this.'],
            'afternoon_start' => ['group' => 'shifts', 'label' => 'Afternoon start', 'value' => '14:00:00', 'value_type' => 'time', 'description' => 'Fallback when roster schedule_range has no parseable start.'],
            'afternoon_end' => ['group' => 'shifts', 'label' => 'Afternoon end', 'value' => '23:00:00', 'value_type' => 'time', 'description' => 'Overtime starts after this.'],
            'graveyard_start' => ['group' => 'shifts', 'label' => 'Graveyard start', 'value' => '22:00:00', 'value_type' => 'time', 'description' => 'Fallback when roster schedule_range has no parseable start.'],
            'graveyard_end' => ['group' => 'shifts', 'label' => 'Graveyard end', 'value' => '06:00:00', 'value_type' => 'time', 'description' => 'Next-day end for overnight shifts.'],
            'night_window_start' => ['group' => 'shifts', 'label' => 'Night window start', 'value' => '22:00', 'value_type' => 'time', 'description' => 'Night differential window (default 10PM – 6AM).'],
            'night_window_end' => ['group' => 'shifts', 'label' => 'Night window end', 'value' => '06:00', 'value_type' => 'time', 'description' => 'Night differential window end (next day).'],
            // Pay calendar
            'pay_date_offset_days' => ['group' => 'calendar', 'label' => 'Pay date offset (days)', 'value' => '3', 'value_type' => 'number', 'description' => 'Pay date = period end + this many days.'],
            'pay_date_skip_sunday' => ['group' => 'calendar', 'label' => 'Skip Sunday pay date', 'value' => '1', 'value_type' => 'boolean', 'description' => 'Push pay date to Monday when it lands on Sunday.'],
            'weekly_periods' => ['group' => 'calendar', 'label' => 'Weekly periods / year', 'value' => '52', 'value_type' => 'number', 'description' => 'Used for accrual scaling.'],
            'semimonthly_periods' => ['group' => 'calendar', 'label' => 'Semi-monthly periods / year', 'value' => '24', 'value_type' => 'number', 'description' => 'Used for accrual scaling.'],
            'monthly_periods' => ['group' => 'calendar', 'label' => 'Monthly periods / year', 'value' => '12', 'value_type' => 'number', 'description' => 'Used for accrual scaling.'],
            // Attendance + payroll controls
            'history_max_days' => ['group' => 'attendance', 'label' => 'History range limit (days)', 'value' => '62', 'value_type' => 'number', 'description' => 'Max from→to range on the attendance history view.'],
            'daily_per_page' => ['group' => 'attendance', 'label' => 'Daily rows per page', 'value' => '15', 'value_type' => 'number', 'description' => 'Attendance daily view pagination.'],
            'history_per_page' => ['group' => 'attendance', 'label' => 'History rows per page', 'value' => '50', 'value_type' => 'number', 'description' => 'Attendance history view pagination.'],
            'accrual_months' => ['group' => 'payroll', 'label' => '13th-month divisor', 'value' => '12', 'value_type' => 'number', 'description' => '13th accrual = monthly equiv ÷ this ÷ periods-per-month.'],
            'ot_warning_hours' => ['group' => 'payroll', 'label' => 'Overtime warning (hours)', 'value' => '40', 'value_type' => 'number', 'description' => 'Payslip warns above this many OT hours.'],
            'payment_ref_prefix' => ['group' => 'payroll', 'label' => 'Payment ref prefix', 'value' => 'PAY-', 'value_type' => 'text', 'description' => 'Auto payment reference starts with this.'],
            'default_payment_method' => ['group' => 'payroll', 'label' => 'Default payment method', 'value' => 'bank', 'value_type' => 'text', 'description' => 'Pre-selected when marking paid.'],
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
