<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSize extends Model
{
    protected $fillable = ['product_id', 'size', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
