<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBom extends Model
{
    protected $table = 'product_bom';

    protected $fillable = [
        'product_id', 'material_id', 'sku_ref', 'name',
        'qty', 'unit', 'category', 'warehouse_note',
        'unit_cost', 'stock_status', 'sort_order',
    ];

    protected $casts = [
        'qty' => 'float',
        'unit_cost' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getLineTotalAttribute(): float
    {
        return $this->qty * $this->unit_cost;
    }
}
