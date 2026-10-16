<?php

namespace App\Models\War;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'floor_id',
        'name',
        'grid_row',
        'grid_col',
        'capacity',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function floor()
    {
        return $this->belongsTo(WarehouseFloor::class, 'floor_id');
    }

    public function shelves()
    {
        return $this->hasMany(WarehouseShelf::class, 'section_id');
    }
    public function stockItemsNoShelf() {
    // Items that belong to this section but have NO specific shelf assigned
    return $this->hasMany(WarehouseStockItem::class, 'section_id')->whereNull('shelf_id');
}
}