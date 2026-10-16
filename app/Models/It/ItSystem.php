<?php

namespace App\Models\It;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItSystem extends Model
{
    protected $table = 'it_systems';

    protected $fillable = [
        'name',
        'system_type',
        'location',
        'host',
        'status',
        'last_checked_at',
        'last_checked_by',
        'notes',
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(ItSystemCheck::class, 'system_id')->latest();
    }

    public function lastChecker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_checked_by');
    }
}
