<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DriverDutyLog extends Model
{
    protected $fillable = [
        'driver_id',
        'status',
        'started_at',
        'ended_at',
        'duration_minutes',
        'latitude',
        'longitude',
        'notes',
        'signature_path',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
