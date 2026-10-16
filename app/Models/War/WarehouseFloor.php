<?php

namespace App\Models\War;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseFloor extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'name',
        'level',
        'grid_rows',
        'grid_cols',
    ];

    protected $casts = [
        'level' => 'integer',
        'grid_rows' => 'integer',
        'grid_cols' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(WarehouseSection::class, 'floor_id')->orderBy('name');
    }
}
