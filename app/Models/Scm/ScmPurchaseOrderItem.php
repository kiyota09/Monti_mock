<?php

namespace App\Models\Scm;

use App\Models\Inv\Material;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScmPurchaseOrderItem extends Model
{
    use HasFactory;

    protected $table = 'scm_purchase_order_items';

    protected $fillable = [
        'scm_purchase_order_id',
        'material_id',
        'warehouse_id', // Added to support material delivery tracking
        'material_name',
        'qty',
        'received_qty',
        'unit',
        'unit_price',
        'total',
    ];

    /**
     * Relationship to the purchase order.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ScmPurchaseOrder::class, 'scm_purchase_order_id');
    }

    /**
     * Relationship to the material (inventory).
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    /**
     * Relationship to the warehouse where this item was received.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}