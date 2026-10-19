<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $fillable = ['truck_number', 'plate_number', 'model', 'year', 'mileage', 'status', 'max_packages', 'remarks'];

    protected $casts = [
        'mileage' => 'decimal:2',
        'max_packages' => 'integer',
    ];

    /**
     * Packages currently loaded on an active delivery for this truck.
     */
    public function loadedPackagesCount(): int
    {
        return (int) $this->deliveries()
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->withCount('packages')
            ->get()
            ->sum('packages_count');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}