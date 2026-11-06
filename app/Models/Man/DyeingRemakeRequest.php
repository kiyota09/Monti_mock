<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class DyeingRemakeRequest extends Model
{
    protected $fillable = [
        'code', 'dyeing_batch_id', 'manufacturing_order_id', 'sales_order_id', 'assigned_to',
        'assigned_by', 'machine_ids', 'requested_weight_kg', 'status',
    ];

    protected $casts = ['machine_ids' => 'array', 'requested_weight_kg' => 'decimal:2'];

    public function batch() { return $this->belongsTo(DyeingBatch::class, 'dyeing_batch_id'); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function fabrics() { return $this->hasMany(DyeingRemakeRequestFabric::class); }
    public function salesOrder() { return $this->belongsTo(\App\Models\Ord\SalesOrder::class); }
}
