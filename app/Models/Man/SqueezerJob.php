<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class SqueezerJob extends Model
{
    protected $fillable = [
        'softener_job_id',
        'machine_id',
        'remarks',
        'operator_id',
        'shift',
        'code',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function softenerJob()
    {
        return $this->belongsTo(SoftenerJob::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function ironJob()
    {
        return $this->hasOne(IronJob::class);
    }
}
