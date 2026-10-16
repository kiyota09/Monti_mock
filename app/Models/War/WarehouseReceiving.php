<?php

namespace App\Models\War;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseReceiving extends Model
{
    use HasFactory;

    protected $table = 'warehouse_receivings';

    protected $fillable = [
        'receiving_number',
        'warehouse_id',
        'scm_purchase_order_id',
        'received_at',
        'received_by',
        'status',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    /**
     * Get the warehouse where items were received.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the Purchase Order associated with this receiving record.
     * FIXED: Added Scm sub-namespace to prevent "Class not found" error.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Scm\ScmPurchaseOrder::class, 'scm_purchase_order_id');
    }

    /**
     * Get the user who processed the receiving.
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Items associated with this receiving transaction.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseReceivingItem::class, 'receiving_id');
    }
}
