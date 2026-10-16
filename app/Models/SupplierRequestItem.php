<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierRequestItem extends Model
{
    protected $fillable = [
        'supplier_request_id',
        'material_name',
        'quantity',
        'unit',
        'unit_price',
        'specs',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SupplierRequest::class, 'supplier_request_id');
    }
}
