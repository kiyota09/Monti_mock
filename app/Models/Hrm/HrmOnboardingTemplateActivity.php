<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class HrmOnboardingTemplateActivity extends Model
{
    protected $table = 'hrm_onboarding_template_activities';
    protected $fillable = [
        'template_id', 'type', 'title', 'description', 'default_method', 'default_duration_minutes',
        'is_required', 'applicant_visible', 'attendance_required',
        'must_complete_before_onboarding_completion', 'is_active', 'sort_order',
    ];
    protected $casts = [
        'is_required' => 'boolean', 'applicant_visible' => 'boolean',
        'attendance_required' => 'boolean',
        'must_complete_before_onboarding_completion' => 'boolean', 'is_active' => 'boolean',
    ];
}
