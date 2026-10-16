<?php

namespace App\Support;

/**
 * Attendance math shared by entry, approval and billing.
 *
 * - hours: derived from time in/out (HH:MM, 24h).
 * - days factor: Present 1, Late 1, Half day 0.5, Absent/Leave 0.
 * - overtime is paid at straight hourly rate (daily rate ÷ 8).
 */
class AttendanceCalc
{
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

    public static function hourlyRate(?float $dailyRate): float
    {
        return round(((float) ($dailyRate ?? 0)) / 8, 2);
    }

    /**
     * Labor for one attendance row. Uses the rate snapshot copied at
     * approval (never the live worker rate) so later rate changes don't
     * alter past billing. Rows without a snapshot contribute 0.
     */
    public static function labor(?string $status, $rateSnapshot, $overtimeHours): float
    {
        $rate = (float) ($rateSnapshot ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round(
            static::daysFactor($status) * $rate
            + ((float) ($overtimeHours ?? 0)) * static::hourlyRate($rate),
            2
        );
    }
}
