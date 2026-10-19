<?php

namespace App\Support;

class PhilippineHolidays
{
    /**
     * Get all Philippine holidays for a given year.
     * Returns array of [date => [name, type, work_policy]]
     */
    public static function getHolidays(int $year): array
    {
        // Sanitize year to prevent malformed strings
        $year = (int) $year;
        if ($year < 1900 || $year > 2100) {
            $year = now()->year;
        }

        $holidays = [];

        // Fixed date holidays
        $fixedHolidays = [
            '01-01' => ['New Year\'s Day', 'regular', 'non_working'],
            '04-09' => ['Araw ng Kagitingan', 'regular', 'non_working'],
            '05-01' => ['Labor Day', 'regular', 'non_working'],
            '06-12' => ['Independence Day', 'regular', 'non_working'],
            '08-21' => ['Ninoy Aquino Day', 'special_non_working', 'non_working'],
            '11-01' => ['All Saints\' Day', 'special_non_working', 'non_working'],
            '11-30' => ['Bonifacio Day', 'regular', 'non_working'],
            '12-08' => ['Feast of the Immaculate Conception', 'special_non_working', 'non_working'],
            '12-25' => ['Christmas Day', 'regular', 'non_working'],
            '12-30' => ['Rizal Day', 'regular', 'non_working'],
            '12-31' => ['Last Day of the Year', 'special_non_working', 'non_working'],
        ];

        foreach ($fixedHolidays as $md => [$name, $type, $policy]) {
            $holidays["{$year}-{$md}"] = [$name, $type, $policy];
        }

        // Movable holidays
        // Maundy Thursday, Good Friday, Black Saturday (Holy Week)
        $easter = self::easterDate($year);
        $maundy = clone $easter;
        $maundy->modify('-3 days');
        $holidays[$maundy->format('Y-m-d')] = ['Maundy Thursday', 'regular', 'non_working'];

        $goodFriday = clone $easter;
        $goodFriday->modify('-2 days');
        $holidays[$goodFriday->format('Y-m-d')] = ['Good Friday', 'regular', 'non_working'];

        $blackSaturday = clone $easter;
        $blackSaturday->modify('-1 day');
        $holidays[$blackSaturday->format('Y-m-d')] = ['Black Saturday', 'special_non_working', 'non_working'];

        // Eid'l Fitr (approximate - usually announced by government)
        // Eid'l Adha (approximate)
        // These are typically declared each year, so we'll leave as placeholders

        // National Heroes Day - Last Monday of August
        $heroesDay = new \DateTime("last monday of august {$year}");
        $holidays[$heroesDay->format('Y-m-d')] = ['National Heroes Day', 'regular', 'non_working'];

        return $holidays;
    }

    /**
     * Calculate Easter date for a given year (Gregorian calendar)
     */
    private static function easterDate(int $year): \DateTime
    {
        $a = $year % 19;
        $b = (int)($year / 100);
        $c = $year % 100;
        $d = (int)($b / 4);
        $e = $b % 4;
        $f = (int)(($b + 8) / 25);
        $g = (int)(($b - $f + 1) / 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = (int)($c / 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = (int)(($a + 11 * $h + 22 * $l) / 451);
        $month = (int)(($h + $l - 7 * $m + 114) / 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTime("{$year}-{$month}-{$day}");
    }

    /**
     * Merge database holidays with calculated Philippine holidays.
     * DB holidays take precedence (user overrides).
     */
    public static function mergeWithCalculated(array $dbHolidays, int $year): array
    {
        $calculated = self::getHolidays($year);
        $merged = $calculated;

        foreach ($dbHolidays as $h) {
            $date = substr((string) $h['date'], 0, 10);
            $merged[$date] = [
                $h['name'],
                $h['type'],
                $h['policy'] ?? 'non_working',
            ];
        }

        return $merged;
    }
}