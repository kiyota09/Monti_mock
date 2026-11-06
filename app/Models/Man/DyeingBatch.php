<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Ord\SalesOrder;
use Illuminate\Database\Eloquent\Model;

class DyeingBatch extends Model
{
    protected $fillable = [
        'code', 'manufacturing_order_id', 'sales_order_id', 'machine_id', 'assigned_to',
        'assigned_by', 'color', 'load_capacity_kg', 'total_weight_kg', 'status',
    ];

    protected $casts = ['load_capacity_kg' => 'decimal:2', 'total_weight_kg' => 'decimal:2'];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function manufacturingOrder() { return $this->belongsTo(ManufacturingOrder::class); }
    public function machine() { return $this->belongsTo(Machine::class); }
    public function operator() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function fabrics() { return $this->hasMany(DyeingBatchFabric::class); }
    public function remakeRequests() { return $this->hasMany(DyeingRemakeRequest::class); }
}
