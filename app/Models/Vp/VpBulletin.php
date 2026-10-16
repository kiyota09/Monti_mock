<?php

namespace App\Models\Vp;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class VpBulletin extends Model
{
    protected $table = 'vp_bulletins';

    protected $fillable = [
        'title', 'body', 'audience', 'priority', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'expires_at' => 'date',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where(fn ($q) => $q
            ->whereNull('expires_at')
            ->orWhereDate('expires_at', '>=', today()));
    }
}
