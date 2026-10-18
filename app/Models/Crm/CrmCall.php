<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal CRM call between two Monti users (voice or video). The row is
 * the call log: `started_at` is when the invite began ringing and `duration`
 * is how long the answered call lasted (seconds).
 */
class CrmCall extends Model
{
    protected $fillable = [
        'caller_id',
        'callee_id',
        'client_id',
        'type',
        'status',
        'started_at',
        'answered_at',
        'ended_at',
        'duration',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration' => 'integer',
    ];

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function callee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
