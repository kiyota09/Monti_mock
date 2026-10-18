<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOnboarding extends Model
{
    protected $table = 'hrm_onboardings';
    protected $fillable = [
        'applicant_id', 'template_id', 'job_posting_id', 'name', 'email', 'position',
        'department', 'employment_type', 'work_mode', 'work_location', 'work_schedule',
        'salary_range', 'start_date', 'expected_start_date', 'expected_completion_date',
        'duration_days', 'duration_started_at', 'duration_reminder_sent_at',
        'assigned_manager', 'status', 'progress',
    ];
    protected $casts = [
        'start_date' => 'date', 'expected_start_date' => 'date', 'expected_completion_date' => 'date',
        'duration_started_at' => 'datetime', 'duration_reminder_sent_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(HrmOnboardingItem::class, 'onboarding_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(HrmOnboardingActivity::class, 'onboarding_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(HrmOnboardingNote::class, 'onboarding_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(HrmOnboardingTemplate::class, 'template_id');
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }
}
