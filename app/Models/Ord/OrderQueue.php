<?php

namespace App\Models\Ord;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'sales_order_id',
        'stage',
        'scm_received_at',
        'inv_checked_at',
        'man_started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scm_received_at' => 'datetime',
        'inv_checked_at' => 'datetime',
        'man_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
