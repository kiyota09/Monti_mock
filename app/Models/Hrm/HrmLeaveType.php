<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class HrmLeaveType extends Model
{
    protected $table = 'hrm_leave_types';
    protected $fillable = ['code', 'name', 'description', 'default_days', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
