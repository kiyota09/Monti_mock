<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    /** Dedicated table — the legacy `attendances` table belongs to HRM. */
    protected $table = 'agency_attendances';

    public const STATUS_PRESENT = 'Present';

    public const STATUS_ABSENT = 'Absent';

    public const STATUS_LATE = 'Late';

    public const STATUS_HALF_DAY = 'Half day';

    public const STATUS_LEAVE = 'Leave';

    public const APPROVAL_DRAFT = 'Draft';

    public const APPROVAL_APPROVED = 'Approved';

    public const APPROVAL_REJECTED = 'Rejected';

    /** Agency confirmation window for Approved rows (days). */
    public const CONFIRM_WINDOW_DAYS = 3;

    protected $fillable = [
        'worker_id', 'request_id', 'date', 'time_in', 'time_out', 'hours',
        'overtime_hours', 'status', 'approval', 'review_reason', 'approved_at',
        'agency_confirmation', 'dispute_reason', 'disputed_at', 'disputed_by_name',
        'rate_snapshot', 'remarks', 'billed_statement_id',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'rate_snapshot' => 'decimal:2',
        'approved_at' => 'datetime',
        'disputed_at' => 'datetime',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ManpowerRequest::class, 'request_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class, 'attendance_id')->latest();
    }

    /**
     * Whether the agency can still Confirm/Dispute this row: Approved,
     * confirmation still Pending, inside the 3-day window.
     */
    public function getConfirmableAttribute(): bool
    {
        if ($this->approval !== self::APPROVAL_APPROVED) {
            return false;
        }
        if (($this->agency_confirmation ?? 'Pending') !== 'Pending') {
            return false;
        }
        if (! $this->approved_at) {
            return true;
        }

        return now()->diffInDays($this->approved_at) <= self::CONFIRM_WINDOW_DAYS;
    }

    /**
     * Display confirmation: explicit state wins; a Pending row past the
     * window counts as confirmed.
     */
    public function getDisplayConfirmationAttribute(): string
    {
        $state = $this->agency_confirmation ?? 'Pending';
        if ($state !== 'Pending') {
            return $state;
        }
        if ($this->approval === self::APPROVAL_APPROVED
            && $this->approved_at
            && now()->diffInDays($this->approved_at) > self::CONFIRM_WINDOW_DAYS) {
            return 'Auto-confirmed';
        }

        return 'Pending';
    }
}
