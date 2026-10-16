<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GovernmentContributionRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'bracket_name', 'min_compensation', 'max_compensation',
        'employee_share', 'employer_share', 'fixed_amount', 'percentage',
        'meta', 'is_active',
    ];

    protected $casts = [
        'min_compensation' => 'decimal:2',
        'max_compensation' => 'decimal:2',
        'employee_share' => 'decimal:4',
        'employer_share' => 'decimal:4',
        'fixed_amount' => 'decimal:2',
        'percentage' => 'decimal:4',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type)->where('is_active', true);
    }
}