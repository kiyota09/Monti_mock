<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmApplicationScreening extends Model
{
    protected $table = 'hrm_application_screenings';
    protected $fillable = [
        'applicant_id', 'job_posting_id', 'resume_reviewed', 'education_verified',
        'work_experience_reviewed', 'skills_reviewed', 'qualifications_met',
        'applicant_info_reviewed', 'result', 'notes', 'rejection_reason', 'screened_by',
    ];
    protected $casts = [
        'resume_reviewed' => 'boolean', 'education_verified' => 'boolean',
        'work_experience_reviewed' => 'boolean', 'skills_reviewed' => 'boolean',
        'qualifications_met' => 'boolean', 'applicant_info_reviewed' => 'boolean',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Core\User::class, 'screened_by');
    }
}
