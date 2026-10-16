<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    // Allow mass assignment for these fields
    protected $fillable = [
        'applicant_id',
        'scheduled_at',
        'interview_type',
        'duration',
        'interviewer',
        'location',
        'notes',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Get the applicant that owns the interview.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }
}
