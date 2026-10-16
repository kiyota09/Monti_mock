<?php

namespace App\Models\War;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'manager',
        'supervisor_id',
        'manager_id',
        'color',
    ];

    protected $casts = [
        'supervisor_id' => 'integer',
        'manager_id' => 'integer',
    ];

    /**
     * Get the supervisor user for this warehouse.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Get the manager user for this warehouse.
     */
    public function managerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Floors in this warehouse (multi-storey support).
     */
    public function floors(): HasMany
    {
        return $this->hasMany(WarehouseFloor::class)->orderBy('level');
    }

    /**
     * Get the sections in this warehouse.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(WarehouseSection::class);
    }

    /**
     * Get all stock items in this warehouse.
     * This checks if the warehouse is "In Use" before deletion.
     */
    public function stockItems(): HasMany
    {
        return $this->hasMany(WarehouseStockItem::class, 'warehouse_id');
    }

    /**
     * Get all receiving records for this warehouse.
     * Updated to the correct namespace used in your Scm logic.
     */
    public function receivings(): HasMany
    {
        // Using the fully qualified namespace to prevent "Class not found" errors
        return $this->hasMany(\App\Models\War\WarehouseReceiving::class, 'warehouse_id');
    }

    /**
     * Get all packages in this warehouse.
     */
    public function packages(): HasMany
    {
        return $this->hasMany(WarehousePackage::class);
    }

    /**
     * Get all rejects from this warehouse.
     */
    public function rejects(): HasMany
    {
        return $this->hasMany(WarehouseReject::class);
    }

    /**
     * Users who have access to this warehouse.
     */
    public function accessibleBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_warehouse_access');
    }
}
