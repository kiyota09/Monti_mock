<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmJobPosting extends Model
{
    protected $table = 'hrm_job_postings';
    protected $fillable = [
        'posting_id', 'title', 'position_id', 'department', 'reports_to', 'employment_type_id',
        'vacancies', 'filled_vacancies', 'salary_min', 'salary_max', 'salary_visibility',
        'hiring_priority', 'status', 'recruiter', 'hiring_manager', 'work_location', 'work_mode',
        'work_schedule', 'shift', 'recruitment_notes', 'internal_notes', 'require_initial_interview',
        'require_final_interview', 'closing_date', 'expected_start_date', 'published_at', 'archived_at',
        'training_required',
    ];
    protected $casts = [
        'require_initial_interview' => 'boolean', 'require_final_interview' => 'boolean',
        'training_required' => 'boolean',
        'closing_date' => 'date', 'expected_start_date' => 'date',
        'published_at' => 'datetime', 'archived_at' => 'datetime',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrmPosition::class, 'position_id');
    }

    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(HrmEmploymentType::class, 'employment_type_id');
    }
}
