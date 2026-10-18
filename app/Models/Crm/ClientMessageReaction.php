<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One emoji reaction a CRM staff member or partner contact placed on a
 * direct message. `reactor_key` (e.g. "crm:5" / "client:owner") keeps each
 * identity to a single toggle per emoji.
 */
class ClientMessageReaction extends Model
{
    protected $fillable = [
        'client_message_id',
        'reactor_type',
        'reactor_key',
        'reactor_name',
        'emoji',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ClientMessage::class, 'client_message_id');
    }
}
