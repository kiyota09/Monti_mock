<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Ord\SalesOrder;
use Illuminate\Database\Eloquent\Model;

class KnittingMachineSetup extends Model
{
    protected $fillable = [
        'code',
        'machine_id',
        'sales_order_id',
        'fabric_id',
        'task_type',
        'settings',
        'status',
        'remarks',
        'operator_id',
        'shift',
        'processed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'processed_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }
}
