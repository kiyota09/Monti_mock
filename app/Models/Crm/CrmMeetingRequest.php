<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A meeting/call request emailed to a client. The client confirms, declines or
 * requests a reschedule from the email; their answer lands in the CRM feedback
 * panel on the Opportunities board.
 */
class CrmMeetingRequest extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'declined', 'reschedule'];

    protected $fillable = [
        'token', 'opportunity_id', 'activity_id', 'client_id', 'lead_id', 'requested_by',
        'type', 'summary', 'meeting_link', 'notes', 'proposed_at', 'status',
        'recipient_name', 'recipient_email',
        'response_place', 'response_message', 'preferred_at', 'responded_at',
    ];

    protected $casts = [
        'proposed_at' => 'datetime',
        'preferred_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            if (empty($request->token)) {
                $request->token = Str::random(48);
            }
        });
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isAnswered(): bool
    {
        return $this->status !== 'pending';
    }
}
