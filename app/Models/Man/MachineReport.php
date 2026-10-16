<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class MachineReport extends Model
{
    protected $fillable = [
        'machine_id',
        'reported_by',
        'issue',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'status' => 'string',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
