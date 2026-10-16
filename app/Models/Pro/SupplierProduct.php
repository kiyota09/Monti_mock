<?php

namespace App\Models\Pro;

use App\Models\Inv\Material;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'material_id',
        'unit_price',
        'is_available',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
