<?php

namespace App\Models\Ord;

use App\Models\Crm\Client;
use App\Models\Ord\PurchaseOrder;
use App\Models\Man\BomRecord;
use App\Models\Man\ManufacturingOrder;
use App\Models\Inv\Product;


use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'client_id',
        'jo_number',
        'control_number',
        'color',
        'quantity',
        'unit_price',
        'total_amount',
        'yarn_type',
        'design',
        'recipe_id',
        'status',
        'pushed_to',
        'inv_check_sufficient',
         'knitting_done_at', 'knitting_done_by',        // ← Added: stores 'SCM' or 'Order Mgmt'
        // ORD lifecycle fields (see 2026_09_08_100000 migration)
        'expected_ship_date',
        'priority',
        'confirmed_at',
        'confirmed_by',
        'production_started_at',
        'production_done_at',
        'delivered_at',
        'cancel_reason',
        'on_hold_reason',
        'created_by',
        // Payment posting (ORD receipt uploads + FIN collection back-sync).
        'payment_status',
        'receipt_file',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'expected_ship_date' => 'date',
        'confirmed_at' => 'datetime',
        'production_started_at' => 'datetime',
        'production_done_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the purchase order that owns the sales order.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the client that owns the sales order.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the recipe (BOM record) associated with the sales order.
     */
    public function recipe()
    {
        return $this->belongsTo(BomRecord::class, 'recipe_id');
    }
    public function manufacturingOrder()
{
    return $this->hasOne(ManufacturingOrder::class, 'sales_order_id');
}
  public function bomRecord()
    {
        return $this->belongsTo(BomRecord::class, 'recipe_id');
    }

    /**
     * ORD lifecycle audit trail (order_status_histories morph).
     */
    public function statusHistory()
    {
        return $this->morphMany(OrderStatusHistory::class, 'orderable')->latest();
    }

    public function returns()
    {
        return $this->hasMany(OrderReturn::class)->latest();
    }

    public function activeManufacturingOrder()
    {
        return $this->hasOne(ManufacturingOrder::class, 'sales_order_id')
            ->whereIn('status', ['pending', 'in_progress'])
            ->latestOfMany();
    }
}