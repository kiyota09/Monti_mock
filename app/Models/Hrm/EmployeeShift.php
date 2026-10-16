<?php

namespace App\Models\Hrm;

use App\Models\Core\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeShift extends Model
{
    use SoftDeletes; // Enables soft deletes for this model

    /**
     * The attributes that are mass assignable.
     * Includes dept_code for filtering and shift_type for the Morning/Afternoon/Graveyard logic.
     */
    protected $fillable = [
        'user_id',
        'dept_code',
        'shift_type',
        'effective_date',
        'schedule_range', // Added to support the '8AM - 5PM' style strings in your controller
        'status', // New field
    ];

    protected $attributes = [
        'status' => 'approved',
    ];

    /**
     * Get the user that owns the shift assignment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include shifts for a specific department.
     * Useful for backend filtering in future reports.
     */
    public function scopeForDepartment($query, $dept)
    {
        return $query->where('dept_code', $dept);
    }
}
