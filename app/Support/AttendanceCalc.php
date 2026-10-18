<?php

namespace App\Support;

/**
 * Attendance math shared by entry, approval and billing.
 *
 * - hours: derived from time in/out (HH:MM, 24h).
 * - days factor: Present 1, Late 1, Half day 0.5, Absent/Leave 0.
 * - billing: each workday's hours are split into regular (up to 8h) and
 *   overtime (the rest), priced at the daily rate ÷ 8 and 1.25× that.
 */
class AttendanceCalc
{
    /** Standard paid hours in a workday. */
    public const STANDARD_DAY_HOURS = 8.0;

    /**
     * Overtime billing multiplier. This is a billing-contract rule, kept
     * separate from Payroll's ot_multiplier (they may differ).
     */
    public const OT_MULTIPLIER = 1.25;

    public static function hours(?string $in, ?string $out): ?float
    {
        if (! $in || ! $out) {
            return null;
        }
        if (! preg_match('/^\d{2}:\d{2}$/', $in) || ! preg_match('/^\d{2}:\d{2}$/', $out)) {
            return null;
        }

        [$h1, $m1] = array_map('intval', explode(':', $in));
        [$h2, $m2] = array_map('intval', explode(':', $out));
        $diff = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);

        // Overnight shifts (out past midnight) roll forward one day.
        if ($diff < 0) {
            $diff += 24 * 60;
        }

        return round($diff / 60, 2);
    }

    public static function daysFactor(?string $status): float
    {
        return match ($status) {
            'Present', 'Late' => 1.0,
            'Half day' => 0.5,
            default => 0.0,
        };
    }

    /** Regular (straight-time) hourly rate — the daily rate ÷ 8. */
    public static function hourlyRate(?float $dailyRate): float
    {
        return round(((float) ($dailyRate ?? 0)) / static::STANDARD_DAY_HOURS, 2);
    }

    /**
     * Split one workday's hours into regular and overtime and price them:
     *
     *   regular hours = min(hours, 8)          × (rate ÷ 8)
     *   overtime hours = max(actual hours − 8, approved OT, 0) × (rate ÷ 8) × multiplier
     *
     * The multiplier is the agency's contract OT rate (default 1.25×).
     *
     * The approved-OT figure is only used when it exceeds what the clock
     * already shows beyond 8h, so it is never double-counted. When no time
     * was clocked but the day still counts as worked (Present/Late/Half day)
     * a full/half day is assumed. Internal figures stay at full precision;
     * only the returned total is meant to be rounded for display.
     *
     * @return array{regular_hours:float,ot_hours:float,regular_charge:float,ot_charge:float,total:float}
     */
    public static function dayCharge(?string $status, $rateSnapshot, $overtimeHours = 0, ?float $hours = null, ?float $otMultiplier = null): array
    {
        $zero = ['regular_hours' => 0.0, 'ot_hours' => 0.0, 'regular_charge' => 0.0, 'ot_charge' => 0.0, 'total' => 0.0];

        $rate = (float) ($rateSnapshot ?? 0);
        if ($rate <= 0) {
            return $zero;
        }

        $hours = (float) ($hours ?? 0);
        if ($hours <= 0) {
            $factor = static::daysFactor($status);
            if ($factor <= 0) {
                return $zero;
            }
            $hours = $factor * static::STANDARD_DAY_HOURS;
        }

        $regularHours = min($hours, static::STANDARD_DAY_HOURS);
        $otHours = max($hours - static::STANDARD_DAY_HOURS, (float) ($overtimeHours ?? 0), 0.0);

        $hourly = $rate / static::STANDARD_DAY_HOURS;
        $otHourly = $hourly * ($otMultiplier ?? static::OT_MULTIPLIER);

        $regularCharge = $regularHours * $hourly;
        $otCharge = $otHours * $otHourly;

        return [
            'regular_hours' => $regularHours,
            'ot_hours' => $otHours,
            'regular_charge' => $regularCharge,
            'ot_charge' => $otCharge,
            'total' => $regularCharge + $otCharge,
        ];
    }

    /**
     * Labor for one attendance row (regular + overtime), rounded to centavos.
     * Uses the rate snapshot copied at approval (never the live worker rate).
     */
    public static function labor(?string $status, $rateSnapshot, $overtimeHours = 0, ?float $hours = null, ?float $otMultiplier = null): float
    {
        return round(static::dayCharge($status, $rateSnapshot, $overtimeHours, $hours, $otMultiplier)['total'], 2);
    }
}
