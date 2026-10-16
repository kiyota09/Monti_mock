<?php

namespace App\Models\Ord;

use App\Models\Crm\Client;
use App\Models\Man\BomRecord;
use App\Models\Man\ManufacturingOrder;
use App\Models\Ord\JobOrder;
use App\Models\Ord\OrderQueue;
use App\Models\Ord\PurchaseOrderItem;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * Workflow Statuses:
     * 'credit_review'            - Initial state for ECO Manager review
     * 'tier_assignment'          - State for HR Manager business tiering
     * 'pending_client_approval'  - Final quote sent to client
     * 'approved'                 - Finalized order
     */
    protected $fillable = [
        'client_id',
        'po_number',
        'subtotal',
        'discount_amount',
        'total_amount',
        'status',
        'tier_level',
        'notes',
        'delivery_date',
        'attachment_path', // Added for manual P.O. uploads
        'control_number',  // Added for the detailed Pending Push table
        'yarn',            // Added for the detailed Pending Push table
        // ORD lifecycle fields (see 2026_09_08_100000 migration)
        'expected_ship_date',
        'priority',
        'confirmed_at',
        'confirmed_by',
        'cancel_reason',
        'on_hold_reason',
        // Payment posting (ORD receipt uploads).
        'payment_status',
        'receipt_file',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_date' => 'date',
        'expected_ship_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    /**
     * Relationship: The items within this purchase order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Relationship: The client who placed the order.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relationship: The queue entry for dispatching to SCM/Order Management.
     * Used by the manufacturing module to track production stages.
     */
    public function queue(): HasOne
    {
        return $this->hasOne(OrderQueue::class, 'purchase_order_id');
    }

    /**
     * Relationship Alias: kept for backward compatibility and clarity 
     * in different modules.
     */
    public function orderQueue(): HasOne
    {
        return $this->queue();
    }

    /**
     * Relationship: Link to Job Orders if applicable.
     */
    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }

    public function manufacturingOrders()
{
    return $this->hasMany(ManufacturingOrder::class);
}

    /**
     * ORD lifecycle audit trail (order_status_histories morph).
     */
    public function statusHistory()
    {
        return $this->morphMany(OrderStatusHistory::class, 'orderable')->latest();
    }

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'purchase_order_id', 'po_number');
    }
}