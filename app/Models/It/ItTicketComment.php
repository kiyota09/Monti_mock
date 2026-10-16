<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItTicketComment extends Model
{
    protected $table = 'it_ticket_comments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(ItTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
