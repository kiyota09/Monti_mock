<?php

namespace App\Models;

use App\Models\Pro\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierRequest extends Model
{
    protected $fillable = [
        'request_number',
        'supplier_id',
        'delivery_date',
        'payment_terms',
        'notes',
        'status',
        'created_by',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierRequestItem::class);
    }
}
