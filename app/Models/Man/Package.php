<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'code',
        'operator_id',
        'shift',
        'packaged_at',
        'status',
        'checked_by',
        'checked_at',
        'check_remarks',
        'manufacturing_order_id',
        'fabric_id',
        'quantity',      // added
    ];

    protected $casts = [
        'packaged_at' => 'datetime',
        'checked_at'  => 'datetime',
        'status'      => 'string',
    ];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function manufacturingOrder()
    {
        return $this->belongsTo(ManufacturingOrder::class);
    }

    public function items()
    {
        return $this->hasMany(PackageItem::class);
    }

    /**
     * Get the fabric that was packaged (if any).
     */
    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }
}