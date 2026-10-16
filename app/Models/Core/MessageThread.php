<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    protected $fillable = ['title', 'photo_path', 'created_by', 'last_message_at'];

    protected $casts = ['last_message_at' => 'datetime'];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? '/storage/'.ltrim($this->photo_path, '/') : null;
    }

    /**
     * Personal = exactly 2 participants and no custom title.
     * Everything else is a group chat.
     */
    public function isGroup(): bool
    {
        if ($this->title) {
            return true;
        }
        return $this->participants->count() !== 2;
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'thread_participants', 'thread_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'thread_id')->oldest();
    }

    public function latestMessage()
    {
        return $this->hasOne(ChatMessage::class, 'thread_id')->latestOfMany();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function displayTitle(int $viewerId): string
    {
        if ($this->title) {
            return $this->title;
        }
        $others = $this->participants->where('id', '!=', $viewerId)->pluck('name')->take(3);
        return $others->isNotEmpty() ? $others->join(', ') : 'Just you';
    }

    public function unreadFor(int $viewerId): int
    {
        $pivot = $this->participants->firstWhere('id', $viewerId)?->pivot;
        $since = $pivot?->last_read_at;
        $query = $this->messages()->where('sender_id', '!=', $viewerId);
        if ($since) {
            $query->where('created_at', '>', $since);
        }
        return $query->count();
    }
}
