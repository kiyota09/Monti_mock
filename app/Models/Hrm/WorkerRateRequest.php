<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerRateRequest extends Model
{
    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'worker_id', 'old_rate', 'new_rate', 'status',
        'requested_by_name', 'review_reason', 'reviewed_by_name',
    ];

    protected $casts = [
        'old_rate' => 'decimal:2',
        'new_rate' => 'decimal:2',
    ];

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
