<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'position', 'employee_type', 'base_salary', 'ot_rate', 'night_rate',
        'late_rate_per_minute', 'sunday_rate', 'is_active',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'ot_rate' => 'decimal:2',
        'night_rate' => 'decimal:2',
        'late_rate_per_minute' => 'decimal:2',
        'sunday_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeForType($query, $type)
    {
        return $query->where('employee_type', $type)->where('is_active', true);
    }
}