<?php

namespace App\Models\Eco;

use Illuminate\Database\Eloquent\Model;

class EcoQuotationItem extends Model
{
    protected $table = 'eco_quotation_items';

    protected $fillable = [
        'eco_quotation_id',
        'product_id',
        'fabric',
        'design',
        'color',
        'kilos',
        'unit_price',
        'price',
    ];

    protected $casts = [
        'kilos'      => 'decimal:2',
        'unit_price' => 'decimal:2',
        'price'      => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function quotation()
    {
        return $this->belongsTo(EcoQuotation::class, 'eco_quotation_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Inv\Product::class, 'product_id');
    }
}