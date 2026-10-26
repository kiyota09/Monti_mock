<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMessageTemplate extends Model
{
    public const PAGE_KEYS = [
        'emails',
        'leads',
        'opportunities',
        'inquiries',
        'approvals',
    ];

    protected $fillable = [
        'user_id',
        'page_key',
        'title',
        'body',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
