<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManpowerProposal extends Model
{
    public const STATUS_PROPOSED = 'Proposed';

    public const STATUS_ACCEPTED = 'Accepted';

    public const STATUS_REJECTED = 'Rejected';

    protected $fillable = [
        'request_id', 'worker_id', 'status',
        'proposed_by', 'proposed_by_name',
        'review_reason', 'reviewed_by_name',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ManpowerRequest::class, 'request_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }
}
