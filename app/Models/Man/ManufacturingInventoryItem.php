<?php

namespace App\Models\Man;

use App\Models\Core\User;
use App\Models\Inv\Material;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManufacturingInventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'control_number',
        'material_id',
        'warehouse_stock_item_id',
        'initial_quantity',
        'remaining_quantity',
        'unit',
        'category',
        'status',
        'total_units',
        'used_units',
        'unit_type',
        'unit_weight',
        'department',
        'received_at',
        'received_from',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'initial_quantity' => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
        'unit_weight' => 'decimal:2',
        'total_units' => 'integer',
        'used_units' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouseStockItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseStockItem::class);
    }

    public function receivedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_from');
    }

    /**
     * Consume a specific number of units (rolls/boxes).
     *
     * @param int $units Number of rolls/boxes to consume
     * @return bool True if consumption succeeded, false otherwise
     */
    public function consumeUnits(int $units): bool
    {
        // Ensure we have roll‑based tracking
        if ($this->total_units === null || $this->total_units <= 0) {
            return false;
        }

        $remainingUnits = $this->total_units - $this->used_units;
        if ($units <= 0 || $units > $remainingUnits) {
            return false;
        }

        // Calculate weight per unit (if not explicitly set, derive from initial data)
        $perUnitWeight = $this->unit_weight;
        if ($perUnitWeight === null) {
            if ($this->total_units > 0 && $this->initial_quantity > 0) {
                $perUnitWeight = $this->initial_quantity / $this->total_units;
            } else {
                // Fallback – cannot determine weight per unit
                return false;
            }
        }

        $consumedWeight = $units * $perUnitWeight;

        $this->used_units += $units;
        $this->remaining_quantity = max(0, $this->remaining_quantity - $consumedWeight);

        // Update status based on remaining stock
        if ($this->remaining_quantity <= 0 || $this->used_units >= $this->total_units) {
            $this->status = 'depleted';
        } else {
            $this->status = 'partial';
        }

        return $this->save();
    }

    /**
     * Accessor for available units (remaining rolls/boxes).
     *
     * @return int|null
     */
    public function getAvailableUnitsAttribute(): ?int
    {
        if ($this->total_units === null) {
            return null;
        }
        return max(0, $this->total_units - $this->used_units);
    }
}