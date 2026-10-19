<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DriverIncident extends Model
{
    protected $fillable = [
        'driver_id',
        'delivery_id',
        'truck_id',
        'type',
        'category',
        'severity',
        'description',
        'location_lat',
        'location_lng',
        'location_text',
        'third_party_info',
        'photo_path',
        'status',
        'reported_at',
    ];

    protected $casts = [
        'location_lat' => 'float',
        'location_lng' => 'float',
        'reported_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }
}
