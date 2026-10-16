<?php

namespace App\Models\War;

use App\Models\Inv\Material;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseReceivingItem extends Model
{
    use HasFactory;

    protected $table = 'warehouse_receiving_items';

    protected $fillable = [
        'receiving_id',
        'material_id',
        'expected_qty',
        'received_qty',
        'rejected_qty',
        'status',
        'reject_reason',
    ];

    /**
     * Get the parent receiving record.
     */
    public function receiving(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceiving::class, 'receiving_id');
    }

    /**
     * Get the material associated with this line item.
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}