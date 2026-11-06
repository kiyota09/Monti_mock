<?php

namespace App\Models\Inv; // Updated to INV (Caps) for Hostinger compatibility

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category',
        'status',
        'colors',
        'product_id',
        'sku',
        'minimum_order_quantity',
    ];

    // ─── CRITICAL FOR JSON STORAGE ───
    protected $casts = [
        'colors' => 'array', // Automatically converts JSON to a PHP Array
        'minimum_order_quantity' => 'decimal:2',
    ];

    // Removed the boot() method – product_id and sku are now generated
    // inside ProductController with robust retry logic and error handling.

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function yarnMaterials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'product_yarns', 'product_id', 'material_id')
            ->withTimestamps();
    }

    public function ecoVariantPrices(): HasMany
    {
        return $this->hasMany(\App\Models\Eco\EcoProductVariantPrice::class);
    }
}
