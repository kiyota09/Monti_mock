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
        'status',
        'scheduled_departure',
        'actual_departure',
        'arrival_time',
        'notes'
    ];

    protected $casts = [
        'scheduled_departure' => 'datetime',
        'actual_departure'    => 'datetime',
        'arrival_time'        => 'datetime',
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
        return $this->belongsToMany(WarehousePackage::class, 'delivery_packages', 'delivery_id', 'warehouse_package_id');
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