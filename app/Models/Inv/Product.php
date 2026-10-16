<?php

namespace App\Models\Inv; // Updated to INV (Caps) for Hostinger compatibility

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'category',
        'status',
        'colors',
        'product_id',
        'sku',
    ];

    // ─── CRITICAL FOR JSON STORAGE ───
    protected $casts = [
        'colors' => 'array', // Automatically converts JSON to a PHP Array
    ];

    // Removed the boot() method – product_id and sku are now generated
    // inside ProductController with robust retry logic and error handling.

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}