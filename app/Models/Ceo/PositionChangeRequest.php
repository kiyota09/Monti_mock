<?php

namespace App\Models\Ceo;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionChangeRequest extends Model
{
    protected $table = 'position_change_requests';

    protected $fillable = [
        'target_user_id',
        'requested_by',
        'requester_role',
        'current_position',
        'requested_position',
        'current_role',
        'requested_role',
        'supervisor_department',
        'action',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public const POSITIONS = ['manager', 'staff', 'secretary', 'special_officer', 'vice_president', 'supervisor'];

    public const SUPERVISOR_DEPARTMENTS = ['knitting', 'dyeing', 'finishing', 'maintenance', 'boiler'];

    public const ACTIONS = ['promote', 'demote', 'assign_supervisor', 'remove_supervisor'];

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
