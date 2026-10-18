<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single direct message between Monti Textiles (CRM) and a business
 * partner (the partner portal). Unlike inquiry conversations, these threads
 * need no inquiry and live one per client company.
 *
 * A row is one of three kinds (`type`): a plain `text` message, a `file` or
 * `image` attachment, or a `call` / video-call record. Calls reuse the same
 * row so the thread, polling feed and call log all stay on one timeline.
 */
class ClientMessage extends Model
{
    protected $fillable = [
        'client_id',
        'sender_type',
        'sender_user_id',
        'sender_contact_id',
        'type',
        'call_type',
        'call_status',
        'call_duration',
        'call_ended_at',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'reply_to_id',
        'is_read',
        'deleted_for_everyone_at',
        'deleted_for_crm',
        'deleted_for_client',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'deleted_for_crm' => 'boolean',
        'deleted_for_client' => 'boolean',
        'call_duration' => 'integer',
        'attachment_size' => 'integer',
        'deleted_for_everyone_at' => 'datetime',
        'call_ended_at' => 'datetime',
    ];

    protected $appends = ['attachment_url', 'is_image', 'is_unsent'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderContact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'sender_contact_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ClientMessage::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ClientMessage::class, 'reply_to_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ClientMessageReaction::class);
    }

    public function isCall(): bool
    {
        return $this->type === 'call';
    }

    public function getIsUnsentAttribute(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? '/storage/' . ltrim($this->attachment_path, '/') : null;
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->attachment_mime, 'image/')
            || in_array(strtolower(pathinfo((string) $this->attachment_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}
