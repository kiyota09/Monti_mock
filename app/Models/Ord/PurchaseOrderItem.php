<?php

namespace App\Models\Ord;

use App\Models\Inv\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit_price',
    ];

    /**
     * Relationship: Connects the item back to the main Purchase Order.
     * This is essential for the ECO Manager to view total order details.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Relationship: Connects the item to the specific Product.
     * Allows the system to display the product name and image in the Order Management UI.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Accessor: Calculate the total price for this specific line item.
     */
    public function getLineTotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }
}
