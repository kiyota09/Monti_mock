<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DyeJobChemical extends Model
{
    use HasFactory;

    protected $fillable = [
        'dye_job_id',
        'inventory_item_id',
        'dye_type',           // material name (denormalised)
        'control_number',     // inventory lot control number (denormalised)
        'quantity_used',      // kg consumed from this lot
    ];

    protected $casts = [
        'quantity_used' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function dyeJob()
    {
        return $this->belongsTo(DyeJob::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(ManufacturingInventoryItem::class, 'inventory_item_id');
    }
}