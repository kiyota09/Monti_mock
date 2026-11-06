<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $fillable = [
        'machine_no',
        'type',
        'power',
        'load_capacity_kg',
        'status',
        'remarks',
    ];

    protected $casts = [
        'status' => 'string',
        'load_capacity_kg' => 'decimal:2',
    ];

    public function fabrics()
    {
        return $this->hasMany(Fabric::class);
    }

    public function dyeJobs()
    {
        return $this->hasMany(DyeJob::class);
    }

    public function softenerJobs()
    {
        return $this->hasMany(SoftenerJob::class);
    }

    public function squeezerJobs()
    {
        return $this->hasMany(SqueezerJob::class);
    }

    public function reports()
    {
        return $this->hasMany(MachineReport::class);
    }

    public function knittingStaffAssignment()
    {
        return $this->hasOne(KnittingMachineStaffAssignment::class);
    }

    public function steamMachines()
    {
        return $this->belongsToMany(self::class, 'boiler_machine_links', 'boiler_machine_id', 'steam_machine_id')
            ->withTimestamps();
    }

    public function steamBoilers()
    {
        return $this->belongsToMany(self::class, 'boiler_machine_links', 'steam_machine_id', 'boiler_machine_id')
            ->withTimestamps();
    }

    public function boilerSteamAvailable(): bool
    {
        return $this->power !== 'Steam' || app(\App\Services\Man\BoilerSteamService::class)->isAvailable();
    }
}
