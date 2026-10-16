<?php

namespace App\Models\Sec;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class SecretaryMemo extends Model
{
    protected $fillable = [
        'ref_no', 'title', 'body', 'audience', 'priority',
        'status', 'published_at', 'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    /**
     * Structured audiences the secretary can target. `user:{id}` targets
     * one specific employee. Anything else (legacy role codes like HRM)
     * still matches by role for backward compatibility.
     */
    public const AUDIENCES = [
        'all', 'managers', 'supervisors', 'staffs', 'special_officers',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Employee IDs targeted by an audience string. Supports legacy
     * single `user:{id}` and multi `users:{id},{id},…`.
     */
    public static function audienceUserIds(?string $audience): array
    {
        $a = trim((string) $audience);
        foreach (['users:', 'user:'] as $prefix) {
            if (str_starts_with(strtolower($a), $prefix)) {
                return collect(explode(',', substr($a, strlen($prefix))))
                    ->map(fn ($v) => (int) trim($v))
                    ->filter(fn ($v) => $v > 0)
                    ->unique()->values()->all();
            }
        }

        return [];
    }

    /**
     * Human-readable audience label. Pass a [id => name] map so
     * specific-employee targets render without extra queries.
     */
    public function audienceLabel(array $namesById = []): string
    {
        $a = trim((string) $this->audience);
        $ids = static::audienceUserIds($a);

        if (! empty($ids)) {
            $names = collect($ids)->map(fn ($id) => $namesById[$id] ?? "Employee #{$id}")->values();
            if ($names->count() === 1) {
                return $names->first();
            }

            return $names->take(2)->join(', ').($names->count() > 2 ? ' +'.($names->count() - 2).' more' : '');
        }

        return match (strtolower($a)) {
            'all' => 'Everyone',
            'managers' => 'All Managers',
            'supervisors' => 'All Supervisors',
            'staffs' => 'All Staff',
            'special_officers' => 'Special Officers',
            default => $a === '' ? 'Everyone' : strtoupper($a),
        };
    }

    /**
     * Whether a memo row targets the given account. CEO and COO always
     * see every memo (oversight) regardless of audience.
     */
    public static function targetsUser(string $audience, User $user): bool
    {
        if (in_array($user->role, ['CEO', 'COO'], true)) {
            return true;
        }

        $a = strtolower(trim($audience));

        if ($a === '' || $a === 'all') {
            return true;
        }
        if (! empty(static::audienceUserIds($audience))) {
            return in_array((int) $user->id, static::audienceUserIds($audience), true);
        }

        return match ($a) {
            'managers' => $user->position === 'manager',
            'supervisors' => (bool) $user->is_manufacturing_supervisor,
            'staffs' => $user->position === 'staff' && ! $user->is_manufacturing_supervisor,
            'special_officers' => $user->position === 'special_officer',
            default => strtoupper($user->role ?? '') === strtoupper($audience),
        };
    }

    public function scopeVisibleTo($query, User $user)
    {
        if (in_array($user->role, ['CEO', 'COO'], true)) {
            return $query->where('status', 'published');
        }

        $orGroups = match (true) {
            $user->position === 'manager' => ['all', 'managers'],
            (bool) $user->is_manufacturing_supervisor => ['all', 'supervisors'],
            $user->position === 'staff' => ['all', 'staffs'],
            $user->position === 'special_officer' => ['all', 'special_officers'],
            default => ['all'],
        };

        return $query->where('status', 'published')->where(function ($q) use ($user, $orGroups) {
            $q->whereIn('audience', $orGroups)
                ->orWhere('audience', 'user:'.$user->id)
                // multi-employee lists: comma-delimited exact match on users:{..}
                ->orWhereRaw("CONCAT(',', audience, ',') LIKE ?", ['%,'.$user->id.',%'])
                // legacy role-code audiences (e.g. HRM, MAN)
                ->orWhereRaw('UPPER(audience) = ?', [strtoupper($user->role ?? '')]);
        });
    }

    /**
     * Active user IDs that must be notified on publish: the audience
     * plus CEO and COO (always informed), minus the publisher.
     */
    public function recipientIds(): array
    {
        $a = strtolower(trim($this->audience ?? 'all'));

        $query = User::where('is_active', true)->where('id', '!=', $this->created_by);

        $specificIds = static::audienceUserIds($this->audience);
        if (! empty($specificIds)) {
            $query->whereIn('id', $specificIds);
        } elseif ($a !== '' && $a !== 'all') {
            $query->where(function ($q) use ($a) {
                match ($a) {
                    'managers' => $q->where('position', 'manager'),
                    'supervisors' => $q->where('is_manufacturing_supervisor', true),
                    'staffs' => $q->where('position', 'staff')->where('is_manufacturing_supervisor', false),
                    'special_officers' => $q->where('position', 'special_officer'),
                    default => $q->whereRaw('UPPER(role) = ?', [strtoupper($this->audience)]),
                };
            });
        }

        $ids = $query->pluck('id')->all();

        $execs = User::where('is_active', true)
            ->whereIn('role', ['CEO', 'COO'])
            ->where('id', '!=', $this->created_by)
            ->pluck('id')->all();

        return array_values(array_unique(array_merge($ids, $execs)));
    }
}
