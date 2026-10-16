<?php

namespace App\Models\Crm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSocialAccount extends Model
{
    protected $fillable = [
        'platform', 'page_id', 'page_name', 'page_url',
        'access_token', 'connected_by', 'last_synced_at', 'last_error',
    ];

    protected $hidden = ['access_token'];

    protected $casts = [
        'access_token' => 'encrypted',
        'last_synced_at' => 'datetime',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
