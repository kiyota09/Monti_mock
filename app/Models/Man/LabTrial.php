<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class LabTrial extends Model
{
    protected $fillable = [
        'code', 'dip_request_id', 'trial_no', 'formula', 'adjustments',
        'delta_e', 'spectro_readings', 'status', 'operator_id', 'shift',
        'processed_at',
    ];

    protected $casts = [
        'formula' => 'array',
        'spectro_readings' => 'array',
        'delta_e' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function dip()
    {
        return $this->belongsTo(LabDipRequest::class, 'dip_request_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
