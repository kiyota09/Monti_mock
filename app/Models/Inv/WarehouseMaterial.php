<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseMaterial extends Model
{
    protected $fillable = ['warehouse_id', 'material_id', 'quantity'];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getStatusAttribute(): string
    {
        $qty = $this->quantity;
        $reorder = $this->material?->reorder_point ?? 0;

        if ($qty <= 0) {
            return 'Out of Stock';
        }
        if ($qty <= $reorder) {
            return 'Low Stock';
        }

        return 'In Stock';
    }
}
