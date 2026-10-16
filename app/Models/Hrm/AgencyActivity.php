<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyActivity extends Model
{
    protected $fillable = [
        'agency_id', 'worker_id', 'request_id', 'statement_id',
        'action', 'details', 'actor_id', 'actor_name', 'reason',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ManpowerRequest::class, 'request_id');
    }
}
