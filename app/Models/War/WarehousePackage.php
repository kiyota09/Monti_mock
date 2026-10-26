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
        'quantity' => 'decimal:2',
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

    /** Loads (deliveries) this package has been placed on. */
    public function deliveries()
    {
        return $this->belongsToMany(\App\Models\Logistics\Delivery::class, 'delivery_packages', 'warehouse_package_id', 'delivery_id')
            ->withPivot('delivery_stop_id')
            ->withTimestamps();
    }

    /**
     * Business client this package is destined for. Resolved through the
     * manufacturing order to its sales order (or purchase order) client.
     */
    public function destinationClientId(): ?int
    {
        $mo = $this->manufacturingOrder;

        if (! $mo) {
            return null;
        }

        $clientId = $mo->relationLoaded('salesOrder')
            ? $mo->salesOrder?->client_id
            : $mo->salesOrder()->value('client_id');

        if (! $clientId) {
            $clientId = $mo->relationLoaded('purchaseOrder')
                ? $mo->purchaseOrder?->client_id
                : $mo->purchaseOrder()->value('client_id');
        }

        return $clientId ? (int) $clientId : null;
    }

    public function destinationClient(): ?\App\Models\Crm\Client
    {
        $id = $this->destinationClientId();

        return $id ? \App\Models\Crm\Client::find($id) : null;
    }
}
