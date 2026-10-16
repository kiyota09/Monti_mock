<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Model;

class HrmOnboardingTemplateItem extends Model
{
    protected $table = 'hrm_onboarding_template_items';
    protected $fillable = [
        'template_id', 'category', 'name', 'description', 'input_type', 'options', 'responsible_party',
        'is_required', 'applicant_visible', 'is_active', 'due_rule_type', 'due_rule_value', 'sort_order',
    ];
    protected $casts = ['is_required' => 'boolean', 'applicant_visible' => 'boolean', 'is_active' => 'boolean', 'options' => 'array'];
}
