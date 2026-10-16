<?php

namespace App\Models\Core;

use App\Models\It\ItTicket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemReport extends Model
{
    public const CATEGORIES = ['bug', 'access', 'data', 'feature', 'other'];

    public const STATUSES = ['open', 'in_progress', 'resolved'];

    protected $fillable = [
        'user_id',
        'ticket_id',
        'subject',
        'category',
        'description',
        'page_url',
        'status',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }
}
