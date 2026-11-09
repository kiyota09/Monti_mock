<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class DyeingMachineStaffOrder extends Model
{
    protected $fillable = [
        'machine_id',
        'stage',
        'staff_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
