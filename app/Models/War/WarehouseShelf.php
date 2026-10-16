<?php

namespace App\Models\War;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WarehouseShelf extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'shelf_number',
    ];

    public function section()
    {
        return $this->belongsTo(WarehouseSection::class, 'section_id');
    }

    public function stockItems()
    {
        return $this->hasMany(WarehouseStockItem::class, 'shelf_id');
    }
}