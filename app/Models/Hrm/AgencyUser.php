<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AgencyUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'agency_id', 'name', 'email', 'password', 'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'agency_id');
    }
}
