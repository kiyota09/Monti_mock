<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class SoftenerJob extends Model
{
    protected $fillable = [
        'fabric_id',
        'machine_id',
        'softener_type',
        'softener_no',
        'remarks',
        'operator_id',
        'shift',
        'code',
        'processed_at',
        'status',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'status' => 'string',
    ];

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function squeezerJob()
    {
        return $this->hasOne(SqueezerJob::class);
    }
}
