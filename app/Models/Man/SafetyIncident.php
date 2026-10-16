<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class SafetyIncident extends Model
{
    protected $fillable = [
        'code', 'incident_type', 'location', 'department', 'incident_date',
        'severity', 'description', 'corrective_action', 'status',
        'operator_id', 'shift', 'processed_at',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
