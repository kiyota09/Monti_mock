<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class DriverVehicleInspection extends Model
{
    protected $fillable = [
        'driver_id',
        'truck_id',
        'delivery_id',
        'type',
        'checklist',
        'defects',
        'severity',
        'photo_path',
        'passed',
        'inspected_at',
    ];

    protected $casts = [
        'checklist' => 'array',
        'passed' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
