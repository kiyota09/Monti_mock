<?php

namespace App\Models\Logistics;


use App\Models\Core\User;
use App\Models\Man\Package;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Logistics\Truck;
use App\Models\Logistics\Conductor;
use App\Models\Logistics\Route;
use App\Models\Logistics\ProofOfDelivery;
use App\Models\Logistics\ConductorReport;
use App\Models\Logistics\Driver;
use App\Models\Logistics\DeliveryPackage;
use App\Models\Logistics\Delivery;
use App\Models\War\WarehousePackage;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_number',
        'truck_id',
        'driver_id',
        'conductor1_id',
        'conductor2_id',
        'route_id',
        'origin_lat',
        'origin_lng',
        'origin_label',
        'status',
        'scheduled_departure',
        'actual_departure',
        'arrival_time',
        'total_distance_km',
        'estimated_minutes',
        'route_geometry',
        'notes',
        'rating',
        'rating_comment',
        'rated_at',
        'rated_by_client_id',
    ];

    protected $casts = [
        'scheduled_departure' => 'datetime',
        'actual_departure'    => 'datetime',
        'arrival_time'        => 'datetime',
        'route_geometry'      => 'array',
        'total_distance_km'   => 'float',
        'estimated_minutes'   => 'integer',
        'rating'              => 'integer',
        'rated_at'            => 'datetime',
    ];

    public function truck()
    {
        return $this->belongsTo(Truck::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function conductor1()
    {
        return $this->belongsTo(Conductor::class, 'conductor1_id');
    }

    public function conductor2()
    {
        return $this->belongsTo(Conductor::class, 'conductor2_id');
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function packages()
    {
        return $this->belongsToMany(WarehousePackage::class, 'delivery_packages', 'delivery_id', 'warehouse_package_id')
            ->withPivot('delivery_stop_id')
            ->withTimestamps();
    }

    /**
     * Ordered list of client drop-off stops for this (possibly multi-client) load.
     */
    public function stops()
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sequence');
    }

    public function ratedByClient()
    {
        return $this->belongsTo(\App\Models\Crm\Client::class, 'rated_by_client_id');
    }

    public function proofOfDelivery()
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function conductorReports()
    {
        return $this->hasMany(ConductorReport::class);
    }
}