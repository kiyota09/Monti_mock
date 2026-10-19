<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Real-ERP pay calendars: weekly, semi-monthly, monthly.
 * - weekly: Mon–Sun weeks. Label "Oct 6–12, 2026".
 * - semi_monthly: 1–15, 16–EOM. Label "Oct 1–15, 2026".
 * - monthly: 1–EOM. Label "Oct 2026".
 * Single identifier stays the human label so legacy rows keep working.
 */
class PayPeriods
{
    public const FREQUENCIES = ['weekly', 'semi_monthly', 'monthly'];

    public static function normalizeFrequency(?string $f): string
    {
        $f = strtolower((string) $f);
        if (in_array($f, ['week', 'weekly'])) return 'weekly';
        if (in_array($f, ['month', 'monthly'])) return 'monthly';
        return 'semi_monthly';
    }

    /** Period counts live in Payroll Settings (weekly/semimonthly/monthly per year). */
    private static function settings(): array
    {
        try {
            return \App\Models\Hrm\PayrollSetting::cached();
        } catch (\Throwable) {
            return [];
        }
    }

    public static function periodsPerYear(string $freq): int
    {
        $s = self::settings();
        return match (self::normalizeFrequency($freq)) {
            'weekly' => (int) ($s['weekly_periods'] ?? 52) ?: 52,
            'monthly' => (int) ($s['monthly_periods'] ?? 12) ?: 12,
            default => (int) ($s['semimonthly_periods'] ?? 24) ?: 24,
        };
    }

    public static function periodsPerMonth(string $freq): float
    {
        return self::periodsPerYear($freq) / 12;
    }

    public static function label(Carbon $start, Carbon $end, ?string $freq = null): string
    {
        $freq = self::normalizeFrequency($freq);
        if ($freq === 'monthly') {
            return $start->format('M Y');
        }
        // weekly + semi-monthly share "M j–j, Y" (en dash).
        if ($start->month === $end->month) {
            return $start->format('M j').'–'.$end->format('j, Y');
        }
        return $start->format('M j').'–'.$end->format('M j, Y');
    }

    public static function make(Carbon $start, Carbon $end, string $freq = 'semi_monthly'): array
    {
        $freq = self::normalizeFrequency($freq);
        $s = self::settings();
        $pay = $end->copy()->addDays((int) ($s['pay_date_offset_days'] ?? 3));
        if (!empty($s['pay_date_skip_sunday'] ?? 1) && $pay->dayOfWeek === Carbon::SUNDAY) $pay->addDay();

        return [
            'label' => self::label($start, $end, $freq),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'pay_date' => $pay->toDateString(),
            'year' => $start->year,
            'month' => $start->month,
            'frequency' => $freq,
            'half' => $start->day === 1 ? 'A' : 'B',
        ];
    }

    public static function forMonth(int $year, int $month, string $freq = 'semi_monthly'): array
    {
        $freq = self::normalizeFrequency($freq);
        if ($freq === 'monthly') {
            $s = Carbon::create($year, $month, 1)->startOfDay();
            return [self::make($s, $s->copy()->endOfMonth()->startOfDay(), 'monthly')];
        }
        if ($freq === 'weekly') {
            return self::weeksForMonth($year, $month);
        }
        $firstA = Carbon::create($year, $month, 1)->startOfDay();
        $endA = Carbon::create($year, $month, 15)->startOfDay();
        $startB = Carbon::create($year, $month, 16)->startOfDay();
        $endB = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();

        return [
            self::make($firstA, $endA, 'semi_monthly'),
            self::make($startB, $endB, 'semi_monthly'),
        ];
    }

    /** Mon–Sun weeks overlapping the month (real-ERP weekly payroll). */
    public static function weeksForMonth(int $year, int $month): array
    {
        $out = [];
        $cursor = Carbon::create($year, $month, 1)->startOfDay()->startOfWeek(Carbon::MONDAY);
        $monthEnd = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
        while ($cursor <= $monthEnd) {
            $weekStart = $cursor->copy();
            $weekEnd = $cursor->copy()->addDays(6);
            // Only weeks that touch this month.
            if ($weekEnd >= Carbon::create($year, $month, 1)->startOfDay() && $weekStart <= $monthEnd) {
                $out[] = self::make($weekStart, $weekEnd, 'weekly');
            }
            $cursor->addWeek();
        }
        return $out;
    }

    /** Full payroll calendar for a year in the given frequency. */
    public static function year(int $year, string $freq = 'semi_monthly'): array
    {
        $freq = self::normalizeFrequency($freq);
        if ($freq === 'weekly') {
            // 52 Mon–Sun weeks spanning the year.
            $out = [];
            $cursor = Carbon::create($year, 1, 1)->startOfDay()->startOfWeek(Carbon::MONDAY);
            $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
            while ($cursor <= $yearEnd) {
                $out[] = self::make($cursor->copy(), $cursor->copy()->addDays(6), 'weekly');
                $cursor->addWeek();
            }
            return $out;
        }
        $out = [];
        for ($m = 1; $m <= 12; $m++) {
            foreach (self::forMonth($year, $m, $freq) as $p) $out[] = $p;
        }
        return $out;
    }

    /** Calendar around today: prev year, current year, next year. */
    public static function calendar(string $freq = 'semi_monthly'): array
    {
        $freq = self::normalizeFrequency($freq);
        $y = (int) now()->year;
        return array_merge(self::year($y - 1, $freq), self::year($y, $freq), self::year($y + 1, $freq));
    }

    public static function currentLabel(string $freq = 'semi_monthly'): string
    {
        $freq = self::normalizeFrequency($freq);
        $today = now()->startOfDay();
        if ($freq === 'monthly') {
            return self::make($today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'monthly')['label'];
        }
        if ($freq === 'weekly') {
            $s = $today->copy()->startOfWeek(Carbon::MONDAY);
            return self::make($s, $s->copy()->addDays(6), 'weekly')['label'];
        }
        $half = $today->day <= 15
            ? self::make($today->copy()->startOfMonth(), $today->copy()->day(15), 'semi_monthly')
            : self::make($today->copy()->day(16), $today->copy()->endOfMonth(), 'semi_monthly');
        return $half['label'];
    }

    /** Infer frequency from a period label's day span (no schema change). */
    public static function frequencyForLabel(string $label): string
    {
        $meta = self::find($label, 'semi_monthly') ?? self::find($label, 'weekly') ?? self::find($label, 'monthly');
        if (!$meta || empty($meta['start']) || empty($meta['end'])) {
            // Monthly labels look like "Oct 2026".
            if (preg_match('/^[A-Z][a-z]{2} \d{4}$/', trim($label))) return 'monthly';
            return 'semi_monthly';
        }
        $days = Carbon::parse($meta['start'])->diffInDays(Carbon::parse($meta['end'])) + 1;
        if ($days >= 28) return 'monthly';
        if ($days <= 8) return 'weekly';
        return 'semi_monthly';
    }

    public static function find(string $label, string $freq = 'semi_monthly'): ?array
    {
        $freq = self::normalizeFrequency($freq);
        $currentLabel = self::currentLabel($freq);
        foreach (self::calendar($freq) as $p) {
            if ($p['label'] === $label) {
                $p['is_current'] = ($label === $currentLabel);
                return $p;
            }
        }
        // Monthly "M Y".
        try {
            if (preg_match('/^([A-Z][a-z]{2}) (\d{4})$/', trim($label), $m)) {
                $start = Carbon::createFromFormat('M Y', "{$m[1]} {$m[2]}")->startOfMonth()->startOfDay();
                $end = $start->copy()->endOfMonth()->startOfDay();
                $p = self::make($start, $end, 'monthly');
                $p['is_current'] = ($label === self::currentLabel('monthly'));
                return $p;
            }
            if (preg_match('/^(\w{3}) (\d+)–(\w{3})? ?(\d+)?, (\d{4})$/u', $label, $m)) {
                // "Oct 6–12, 2026" or cross-month "Sep 29–Oct 5, 2026".
                $mon1 = $m[1]; $d1 = $m[2]; $mon2 = $m[3] ?: $m[1]; $d2 = $m[4]; $yr = $m[5];
                $start = Carbon::createFromFormat('M j, Y', "{$mon1} {$d1}, {$yr}")->startOfDay();
                $end = Carbon::createFromFormat('M j, Y', "{$mon2} {$d2}, {$yr}")->startOfDay();
                $f = self::frequencyForLabel($label) === 'monthly' ? 'monthly' : $freq;
                $p = self::make($start, $end, $f);
                $p['is_current'] = ($label === self::currentLabel($f));
                return $p;
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}
