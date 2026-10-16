<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOnboardingActivity extends Model
{
    protected $table = 'hrm_onboarding_activities';
    protected $fillable = [
        'onboarding_id', 'template_activity_id', 'title', 'type', 'description',
        'schedule_date', 'start_time', 'end_time', 'method', 'location', 'meeting_link',
        'facilitator', 'organizer', 'status', 'attendance_status', 'is_required', 'applicant_visible',
    ];
    protected $casts = ['schedule_date' => 'date', 'is_required' => 'boolean', 'applicant_visible' => 'boolean'];

    public function onboarding(): BelongsTo
    {
        return $this->belongsTo(HrmOnboarding::class, 'onboarding_id');
    }

    public function templateActivity(): BelongsTo
    {
        return $this->belongsTo(HrmOnboardingTemplateActivity::class, 'template_activity_id');
    }
}
