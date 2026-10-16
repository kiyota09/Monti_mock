<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $table = 'materials';

    protected $fillable = [
        'mat_id',
        'name',
        'category',
        'unit',
        'reorder_point',
        'unit_cost',
    ];

    protected $casts = [
        'unit_cost' => 'float',
        'reorder_point' => 'integer',
    ];

    /**
     * Relationship: Get all actual receiving logs for this material.
     * This allows us to track which specific Warehouse received the goods.
     */
    public function receivingItems(): HasMany
    {
        // This links to the items inside the WarehouseReceiving records
        return $this->hasMany(\App\Models\War\WarehouseReceivingItem::class, 'material_id');
    }

    /**
     * Relationship: Get all purchase order items associated with this material.
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(\App\Models\Scm\ScmPurchaseOrderItem::class, 'material_id');
    }

    /**
     * Get the stock items (actual warehouse inventory) for this material.
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(\App\Models\War\WarehouseStockItem::class, 'material_id');
    }

    /**
     * Get the BOM entries where this material is used as a component.
     */
    public function productBoms(): HasMany
    {
        return $this->hasMany(\App\Models\Inv\ProductBom::class, 'material_id');
    }

    /**
     * Legacy relationship – kept for backward compatibility if needed.
     */
    public function warehouseMaterials(): HasMany
    {
        return $this->hasMany(\App\Models\Inv\WarehouseMaterial::class);
    }

    /**
     * Legacy relationship – kept for backward compatibility.
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\War\Warehouse::class, 'warehouse_materials')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Auto-generate the next material ID (e.g., MAT-001, MAT-002...).
     */
    public static function nextMatId(): string
    {
        $last = static::orderByDesc('id')->value('mat_id');
        if (! $last) {
            return 'MAT-001';
        }
        $num = (int) substr($last, 4) + 1;
        return 'MAT-'.str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}