<?php

namespace App\Models\Man;

use Illuminate\Database\Eloquent\Model;

class Machine extends Model
{
    protected $fillable = [
        'machine_no',
        'type',
        'status',
        'remarks',
    ];

    protected $casts = [
        'status' => 'string',
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
}
