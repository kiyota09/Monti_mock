<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    protected $fillable = [
        'code', 'dip_request_id', 'test_type', 'method', 'rating',
        'result', 'operator_id', 'shift', 'processed_at',
    ];

    protected $casts = [
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
