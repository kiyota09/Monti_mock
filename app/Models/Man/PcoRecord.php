<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class PcoRecord extends Model
{
    protected $fillable = [
        'code', 'record_type', 'location', 'parameter', 'value', 'unit',
        'standard_limit', 'compliant', 'recorded_date', 'remarks',
        'operator_id', 'shift', 'processed_at',
    ];

    protected $casts = [
        'compliant' => 'boolean',
        'recorded_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
