<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManStaffTransferRequest extends Model
{
    protected $fillable = [
        'requester_supervisor_id',
        'source_supervisor_id',
        'staff_user_id',
        'source_department',
        'target_department',
        'target_role',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_supervisor_id');
    }

    public function sourceSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'source_supervisor_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
