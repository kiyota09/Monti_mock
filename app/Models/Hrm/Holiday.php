<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'holiday_name',
        'holiday_type',
        'work_policy',
        'premium_rate',
        'status', // New field
    ];

    protected $attributes = [
        'status' => 'approved',
    ];

    /**
     * Non-working holidays in [from, to], keyed by Y-m-d:
     * [date => ['name' => ..., 'type' => ...]].
     * A day is non-working when its working policy is `non_working`
     * (legacy `special_working` rows always count as working days).
     */
    public static function nonWorkingMap(string $from, string $to): array
    {
        return self::whereBetween('holiday_date', [$from, $to])
            ->get(['holiday_date', 'holiday_name', 'holiday_type', 'work_policy'])
            ->filter(fn ($h) => ($h->work_policy ?? 'non_working') === 'non_working'
                && $h->holiday_type !== 'special_working')
            ->mapWithKeys(fn ($h) => [
                substr((string) $h->holiday_date, 0, 10) => [
                    'name' => $h->holiday_name,
                    'type' => $h->holiday_type,
                ],
            ])->all();
    }

    /**
     * Every holiday in [from, to] with type + working policy,
     * keyed by Y-m-d: [date => ['name' => ..., 'type' => ..., 'policy' => ...]].
     */
    public static function rangeMap(string $from, string $to): array
    {
        return self::whereBetween('holiday_date', [$from, $to])
            ->get(['holiday_date', 'holiday_name', 'holiday_type', 'work_policy'])
            ->mapWithKeys(fn ($h) => [
                substr((string) $h->holiday_date, 0, 10) => [
                    'name' => $h->holiday_name,
                    'type' => $h->holiday_type,
                    'policy' => $h->work_policy ?? 'non_working',
                ],
            ])->all();
    }

    public static function isNonWorkingDay(string $date): bool
    {
        $h = self::where('holiday_date', substr($date, 0, 10))
            ->first(['holiday_type', 'work_policy']);
        if (! $h) return false;

        return ($h->work_policy ?? 'non_working') === 'non_working'
            && $h->holiday_type !== 'special_working';
    }
}
