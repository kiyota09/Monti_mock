<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = ['thread_id', 'sender_id', 'body', 'file_path', 'file_name', 'file_type'];

    protected $appends = ['file_url', 'is_image'];

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? '/storage/'.ltrim($this->file_path, '/') : null;
    }

    public function getIsImageAttribute(): bool
    {
        return $this->file_type ? str_starts_with($this->file_type, 'image/') : false;
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
