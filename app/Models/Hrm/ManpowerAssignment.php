<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManpowerAssignment extends Model
{
    protected $fillable = [
        'request_id', 'worker_id', 'assigned_by', 'assigned_by_name',
        'assigned_at', 'released_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
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
