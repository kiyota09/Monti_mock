<?php

namespace App\Models\Eco;

use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends Model
{
    protected $table = 'conversation_messages';
    protected $fillable = [
        'inquiry_id',
        'sender_type',
        'message',
        'attachment',
        'reject_reason',      // <--- If this is missing, the database stays null
        'request_new_quote',  // <--- If this is missing, the boolean won't save
        'meeting_data',
        'is_system_event',
        // Fabric sample loop: lab→CRM handoff stays hidden until forwarded,
        // and thread messages can be linked to their sample round.
        'visible_to_client',
        'sample_request_id',
    ];

    protected $casts = [
        'meeting_data' => 'array',
        'is_system_event' => 'boolean',
        'visible_to_client' => 'boolean',
    ];

    public function sampleRequest()
    {
        return $this->belongsTo(\App\Models\Crm\FabricSampleRequest::class, 'sample_request_id');
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }
    public function attachments()
    {
        return $this->hasMany(ConversationAttachment::class, 'conversation_message_id');
    }
}
