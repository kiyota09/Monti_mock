<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmLeaveBalance extends Model
{
    protected $table = 'hrm_leave_balances';
    protected $fillable = ['user_id', 'leave_type_id', 'year', 'entitled', 'used'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(HrmLeaveType::class, 'leave_type_id');
    }

    public function getRemainingAttribute(): int
    {
        return max(0, (int) $this->entitled - (int) $this->used);
    }
}
