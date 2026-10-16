<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItChange extends Model
{
    protected $table = 'it_changes';

    protected $fillable = [
        'change_no',
        'title',
        'description',
        'change_type',
        'risk',
        'status',
        'scheduled_start',
        'scheduled_end',
        'implemented_by',
        'approved_by',
        'approved_at',
        'rollback_plan',
        'completion_notes',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public const OPEN_STATUSES = ['draft', 'pending_approval', 'approved', 'in_progress'];

    public function implementer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'implemented_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
