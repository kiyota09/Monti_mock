<?php

namespace App\Models\Eco;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ConversationAttachment extends Model
{
    protected $table = 'eco_conversation_attachments';

    protected $fillable = [
        'conversation_message_id',
        'file_path',
        'file_name',
        'file_type',
        'approved_by_client',
        'is_po',
    ];

    protected $casts = [
        'approved_by_client' => 'boolean',
        'is_po' => 'boolean',
    ];

    protected $appends = ['url'];

    /**
     * Get the full URL for the stored file.
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Relationship to the parent message.
     */
    public function message()
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    /**
     * Check if this attachment has been approved by the client.
     */
    public function isApproved(): bool
    {
        return $this->approved_by_client;
    }

    /**
     * Check if this attachment is a Purchase Order.
     */
    public function isPO(): bool
    {
        return $this->is_po;
    }
}