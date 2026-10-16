<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Manpower request to an agency (HRM > Requests).
 *
 * Stored status: Draft|Open|Cancelled|Closed. The in-progress labels are
 * derived from assigned vs needed and never set by hand:
 *   stored Open + 0 filled        → Open
 *   stored Open + some filled      → Partially filled
 *   stored Open + needed reached   → Filled
 */
class ManpowerRequest extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_OPEN = 'Open';

    public const STATUS_PARTIAL = 'Partially filled';

    public const STATUS_FILLED = 'Filled';

    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUS_CLOSED = 'Closed';

    protected $fillable = [
        'code', 'department', 'position_skill', 'headcount',
        'start_date', 'end_date', 'shift', 'notes',
        'agency_id', 'status', 'cancel_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'headcount' => 'integer',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ManpowerAssignment::class, 'request_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(ManpowerAssignment::class, 'request_id')->whereNull('released_at');
    }

    public function getFilledCountAttribute(): int
    {
        if ($this->relationLoaded('activeAssignments')) {
            return $this->activeAssignments->count();
        }

        return $this->activeAssignments()->count();
    }

    public function getEffectiveStatusAttribute(): string
    {
        if (in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED, self::STATUS_CLOSED], true)) {
            return $this->status;
        }

        $filled = $this->filled_count;
        $needed = max(1, (int) $this->headcount);

        if ($filled >= $needed) {
            return self::STATUS_FILLED;
        }
        if ($filled > 0) {
            return self::STATUS_PARTIAL;
        }

        return self::STATUS_OPEN;
    }
}
