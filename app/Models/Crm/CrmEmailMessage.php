<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmEmailMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'user_id',
        'direction',
        'type',
        'from_email',
        'from_name',
        'to_email',
        'to_name',
        'subject',
        'body',
        'message_id',
        'in_reply_to',
        'status',
        'error',
        'sent_at',
        'read_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CrmEmailThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
