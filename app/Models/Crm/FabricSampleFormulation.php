<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use App\Models\Eco\ConversationMessage;
use Illuminate\Database\Eloquent\Model;

class FabricSampleFormulation extends Model
{
    protected $fillable = [
        'fabric_sample_request_id', 'version', 'formula', 'attachment_path', 'attachment_name',
        'attachment_mime', 'submitted_by', 'status', 'reviewed_by', 'reviewed_at',
        'review_notes', 'conversation_message_id',
    ];

    protected $casts = [
        'formula' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function sampleRequest()
    {
        return $this->belongsTo(FabricSampleRequest::class, 'fabric_sample_request_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function conversationMessage()
    {
        return $this->belongsTo(ConversationMessage::class);
    }
}
