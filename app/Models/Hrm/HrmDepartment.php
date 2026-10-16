<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmDepartment extends Model
{
    protected $table = 'hrm_departments';
    protected $fillable = ['code', 'name', 'category', 'description', 'head_id', 'status', 'archived_at', 'created_by'];
    protected $casts = ['archived_at' => 'datetime'];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(HrmPosition::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'hrm_department_id');
    }
}
