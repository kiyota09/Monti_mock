<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'link_route', 'memo_id', 'is_read', 'created_by',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Named `notify` (not `push`) — Eloquent already defines a non-static
     * push() method, so a static push() is a fatal error.
     */
    public static function notify(int $userId, string $type, string $title, ?string $body = null, ?string $linkRoute = null, ?int $createdBy = null, ?int $memoId = null): self
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link_route' => $linkRoute,
            'memo_id' => $memoId,
            'is_read' => false,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }
}
