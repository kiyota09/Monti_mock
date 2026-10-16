<?php

namespace App\Models\Inv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = ['name', 'location', 'manager', 'color'];

    public function warehouseMaterials(): HasMany
    {
        return $this->hasMany(WarehouseMaterial::class);
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'warehouse_materials')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}
