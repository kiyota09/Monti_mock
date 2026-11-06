<?php

namespace App\Models\Eco;

use App\Models\Inv\Material;
use App\Models\Inv\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcoProductVariantPrice extends Model
{
    protected $fillable = [
        'product_id',
        'material_id',
        'color_group',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
