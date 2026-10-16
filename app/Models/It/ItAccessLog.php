<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItAccessLog extends Model
{
    protected $table = 'it_access_logs';

    public const ACTIONS = [
        'account.disabled',
        'account.enabled',
        'account.suspended',
        'account.restored',
        'position.updated',
        'modules.updated',
        'pages.updated',
    ];

    protected $fillable = [
        'actor_id',
        'target_user_id',
        'action',
        'details',
        'meta',
        'ip_address',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
