<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceAdjustment extends Model
{
    protected $fillable = [
        'attendance_id', 'changes', 'reason', 'actor_id', 'actor_name',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class, 'attendance_id');
    }
}
