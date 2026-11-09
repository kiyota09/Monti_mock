<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    public const TYPE_CIRCULAR_KNITTING = 'circular_knitting';
    public const TYPE_FLATBED_KNITTING = 'flatbed_knitting';

    public const QUALIFICATION_BLEACHING = 'bleaching';
    public const QUALIFICATION_DYEING = 'dyeing';
    public const QUALIFICATION_PRE_TREATMENT = 'pre_treatment';
    public const QUALIFICATION_POST_TREATMENT = 'post_treatment';

    public const TEMPERATURE_HOT = 'Hot';
    public const TEMPERATURE_COLD = 'Cold';

    protected $fillable = [
        'machine_no',
        'type',
        'qualification',
        'power',
        'temperature',
        'load_capacity_kg',
        'needle_count',
        'status',
        'remarks',
    ];

    protected $casts = [
        'status' => 'string',
        'load_capacity_kg' => 'decimal:2',
        'needle_count' => 'integer',
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
