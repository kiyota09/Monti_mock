<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmPosition extends Model
{
    protected $table = 'hrm_positions';
    protected $fillable = [
        'code', 'name', 'department_id', 'reports_to_id', 'management_level',
        'organization_level', 'description', 'responsibilities', 'qualifications',
        'required_skills', 'salary_min', 'salary_max', 'overtime_eligible', 'approved_headcount', 'status', 'archived_at',
    ];
    protected $casts = ['overtime_eligible' => 'boolean', 'archived_at' => 'datetime'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_id');
    }

    public function holders(): HasMany
    {
        return $this->hasMany(User::class, 'hrm_position_id');
    }
}
