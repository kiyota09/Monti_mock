<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'url'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // No global scope – ordering is handled in the relationship
}