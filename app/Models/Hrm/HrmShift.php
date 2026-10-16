<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HrmShift extends Model
{
    protected $fillable = [
        'code',
        'name',
        'shift_type',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'working_days',
        'is_active',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_active' => 'boolean',
    ];

    public const TYPES = ['Fixed', 'Flexible', 'Rotating'];

    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'hrm_shift_user', 'shift_id', 'user_id')->withTimestamps();
    }

    public static function nextCode(): string
    {
        $max = self::where('code', 'like', 'SHF-%')->get(['code'])
            ->map(fn ($r) => (int) preg_replace('/\D/', '', (string) $r->code))
            ->max() ?? 0;

        return 'SHF-' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function breakLabel(): string
    {
        $m = (int) ($this->break_minutes ?? 0);
        if ($m <= 0) {
            return '—';
        }
        if ($m % 60 === 0) {
            $h = (int) ($m / 60);

            return $h . ($h === 1 ? ' hr' : ' hrs');
        }

        return $m . ' min';
    }

    public function graceLabel(): string
    {
        $m = (int) ($this->grace_minutes ?? 0);

        return $m <= 0 ? '—' : $m . ' min';
    }

    public function workingDaysLabel(): string
    {
        $days = $this->working_days ?? [];
        if (! is_array($days) || empty($days)) {
            return '—';
        }
        $order = array_flip(self::DAYS);
        usort($days, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));
        if (count($days) === 5 && ! array_diff(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], $days)) {
            return 'Monday–Friday';
        }
        if (count($days) === 7) {
            return 'Everyday';
        }

        return implode(', ', array_map(fn ($d) => substr($d, 0, 3), $days));
    }
}
