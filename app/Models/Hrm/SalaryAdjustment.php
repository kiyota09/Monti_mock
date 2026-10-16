<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryAdjustment extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING = 'Pending Approval';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_SCHEDULED = 'Scheduled';
    public const STATUS_APPLIED = 'Applied';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_CANCELLED = 'Cancelled';

    public const OPEN_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULED,
    ];

    public const TYPES = [
        'Merit Increase', 'Annual Review', 'Promotion',
        'Probation Pass', 'Allowance Change', 'Market Adjustment',
    ];

    protected $fillable = [
        'user_id', 'old_salary', 'new_salary', 'adjustment_type',
        'reason', 'notes', 'effective_date', 'status',
        'requested_by', 'approved_by', 'decided_at',
        'document_path', 'applied_at',
    ];

    protected $casts = [
        'old_salary' => 'float',
        'new_salary' => 'float',
        'effective_date' => 'date',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SalaryAdjustmentApproval::class, 'salary_adjustment_id')->latest();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
