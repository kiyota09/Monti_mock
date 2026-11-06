<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class BleachingJob extends Model
{
    protected $fillable = [
        'fabric_id',
        'bleaching_method',
        'remarks',
        'operator_id',
        'shift',
        'code',
        'processed_at',
        'status',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function fabric()
    {
        return $this->belongsTo(Fabric::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
