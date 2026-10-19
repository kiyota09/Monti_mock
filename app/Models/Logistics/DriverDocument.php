<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DriverDocument extends Model
{
    protected $fillable = [
        'driver_id',
        'document_type',
        'title',
        'file_path',
        'number',
        'issued_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function getExpiringSoonAttribute(): bool
    {
        return $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->expires_at->lessThanOrEqualTo(now()->addDays(30));
    }
}
