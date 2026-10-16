<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCase extends Model
{
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected $fillable = [
        'client_id', 'feedback_id', 'subject', 'category', 'severity',
        'status', 'owner_id', 'resolution_notes', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(CrmFeedback::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
