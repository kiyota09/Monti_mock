<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmRegistrationInvite extends Model
{
    protected $fillable = [
        'token',
        'email',
        'name',
        'thread_id',
        'sent_by',
        'lead_id',
        'opportunity_id',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CrmEmailThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && ! $this->isExpired();
    }
}
