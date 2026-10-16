<?php

namespace App\Models\War;

use App\Models\Core\User;
use App\Models\Man\ManufacturingOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Inv\Product;
use Illuminate\Database\Eloquent\Model;

class WarehousePackage extends Model
{
    protected $fillable = [
        'package_number',
        'manufacturing_order_id',
        'product_id',
        'quantity',
        'status',
        'pushed_at',
        'pushed_by',
    ];

    protected $casts = [
        'pushed_at' => 'datetime',
    ];

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function pushedBy()
    {
        return $this->belongsTo(User::class, 'pushed_by');
    }
}
