<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $fillable = ['truck_number', 'plate_number', 'model', 'year', 'mileage', 'status', 'max_packages', 'max_load_kg', 'remarks', 'image_path'];

    protected $casts = [
        'mileage' => 'decimal:2',
        'max_packages' => 'integer',
        'max_load_kg' => 'decimal:2',
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

    public function loadedWeightKg(): float
    {
        return (float) $this->deliveries()
            ->whereIn('status', ['dispatched', 'in_transit'])
            ->with('packages')
            ->get()
            ->sum(fn ($delivery) => $delivery->packages->sum(fn ($package) => (float) ($package->weight_kg ?? 0)));
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }
}
