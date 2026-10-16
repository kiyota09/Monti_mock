<?php

namespace App\Models\Ord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrder extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'purchase_order_id',
        'jo_number',        // Added: Shared batch number
        'control_number',   // Added: Unique per color row
        'yarn_type',        // Renamed from 'yarn' to match migration
        'color',
        'design',
        'quantity',
        'description',
        'status'            // 'pending', 'dispatched', etc.
    ];

    /**
     * Relationship: Link back to the parent Purchase Order.
     * This allows us to access client info: $jobOrder->purchaseOrder->client
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}