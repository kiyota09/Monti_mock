<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItTicket extends Model
{
    protected $table = 'it_tickets';

    protected $fillable = [
        'ticket_no',
        'title',
        'description',
        'category',
        'system_area',
        'priority',
        'status',
        'requester_id',
        'requester_name',
        'assignee_id',
        'location',
        'sla_due_at',
        'resolved_at',
        'closed_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** Resolve-time SLA in hours per priority (ITIL-style targets). */
    public const SLA_HOURS = [
        'P1' => 8,    // critical / plant-stopping
        'P2' => 24,   // high
        'P3' => 72,   // normal
        'P4' => 120,  // low
    ];

    public const OPEN_STATUSES = ['open', 'assigned', 'in_progress', 'pending', 'reopened'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ItTicketComment::class, 'ticket_id')->latest();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeBreached($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function getIsBreachedAttribute(): bool
    {
        return $this->is_open && $this->sla_due_at && $this->sla_due_at->isPast();
    }
}
