<?php

namespace App\Models\War;

use Illuminate\Database\Eloquent\Model;

class WarehouseReject extends Model
{
    // Status constants
    const STATUS_PENDING_RETURN = 'pending_return';
    const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'rejectable_type',
        'rejectable_id',
        'source',
        'warehouse_id',
        'quantity',
        'unit',
        'reason',
        'status',
    ];

    /**
     * Get the parent rejectable model (Fabric, etc.).
     */
    public function rejectable()
    {
        return $this->morphTo();
    }

    /**
     * Get the warehouse where the rejected item is stored.
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Scope a query to only include pending returns.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_RETURN);
    }
}