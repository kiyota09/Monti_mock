<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class ClientCallSignal extends Model
{
    protected $table = 'client_call_signals';

    public const UPDATED_AT = null;

    protected $fillable = [
        'client_message_id',
        'sender_side',
        'kind',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(ClientMessage::class, 'client_message_id');
    }
}
