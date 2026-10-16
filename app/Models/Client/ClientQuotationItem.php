<?php

namespace App\Models\Client;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientQuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'product_id',
        'product_sku',
        'product_name',
        'product_description',
        'technical_specs',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'discount',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'line_total' => 'float',
    ];

    public function quotation()
    {
        return $this->belongsTo(ClientQuotation::class, 'quotation_id');
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Inv\Product::class, 'product_id');
    }
}
