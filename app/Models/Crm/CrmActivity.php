<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmActivity extends Model
{
    public const TYPES = ['call', 'meeting', 'reminder', 'todo', 'note', 'task', 'email'];
    public const STATUSES = ['planned', 'done', 'cancelled'];

    protected $fillable = [
        'client_id', 'opportunity_id', 'lead_id', 'type', 'subject', 'summary',
        'body', 'notes', 'due_at', 'due_date', 'meeting_link', 'done_at', 'status', 'owner_id', 'assigned_to',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'due_date' => 'datetime',
        'done_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** Meeting/call request emails sent for this activity (newest first). */
    public function meetingRequests(): HasMany
    {
        return $this->hasMany(CrmMeetingRequest::class, 'activity_id')->latest('id');
    }

    /** Canonical due moment (new due_date wins, legacy due_at fallback). */
    public function getDueAttribute()
    {
        return $this->due_date ?? $this->due_at;
    }

    /** Canonical title (new summary wins, legacy subject fallback). */
    public function getTitleAttribute(): ?string
    {
        return $this->summary ?? $this->subject;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->done_at === null && $this->due_at !== null && $this->due_at->isPast();
    }
}
