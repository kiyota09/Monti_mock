<?php

namespace App\Models\Man;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;

class KnittingMachineStaffAssignment extends Model
{
    protected $fillable = ['machine_id', 'staff_id', 'assigned_by'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
